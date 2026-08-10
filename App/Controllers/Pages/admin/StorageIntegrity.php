<?php

declare(strict_types=1);

namespace PressDo\App\Controllers\Pages\admin;

use InvalidArgumentException;
use PDO;
use PressDo\App\Core\Controller;
use PressDo\App\Core\ProjectPaths;
use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\Languages;
use PressDo\App\Http\Security\CsrfTokenManager;
use PressDo\App\Infrastructure\Files\JsonLineAuditLogger;
use PressDo\App\Models\ACL;
use PressDo\App\Services\File\FileIntegrityScanner;
use PressDo\App\Services\File\FileObjectResolver;
use PressDo\App\Services\File\FileObjectState;
use PressDo\App\Services\File\OrphanObjectCleaner;
use PressDo\App\Services\File\OrphanObjectCleanupException;
use PressDo\App\Services\File\OrphanObjectScanner;
use PressDo\App\Services\File\PdoFileIntegrityRepository;
use PressDo\App\Services\File\PdoObjectMutationLock;
use PressDo\App\Services\File\PdoObjectReferenceRepository;
use PressDo\App\Services\Uploaders\ObjectKey;
use PressDo\App\Services\Uploaders\ObjectStorageFactory;
use PressDo\App\Services\Uploaders\ObjectStorageInterface;
use Throwable;

final class StorageIntegrity extends Controller
{
    private const PAGE_SIZE = 25;

    private const CLEANUP_TOKEN = 'storage_cleanup_token';

    /** @return array<string, mixed> */
    public function makeData(): array
    {
        if (!$this->canDiagnoseStorage()) {
            return [
                'view_name' => 'error',
                'title' => Languages::get('page', 'error'),
                'data' => ['code' => 'no_permission'],
            ];
        }

        $offset = $this->offset();
        $items = [];
        $total = 0;
        $error = null;
        $orphanItems = [];
        $orphanScanned = 0;
        $ignoredRecent = 0;
        $ignoredUnmanaged = 0;
        $nextObjectCursor = null;
        $actionError = null;
        $deletedObject = null;
        $csrf = new CsrfTokenManager();
        $token = $csrf->issue($this->session, self::CLEANUP_TOKEN);

        try {
            $storageType = Config::get('storage.type');
            if (!is_string($storageType)) {
                throw new \RuntimeException('Object storage type is not configured.');
            }
            $storage = ObjectStorageFactory::create($storageType);
            $database = Database::getInstance();

            if ($this->request->isMethod('POST')) {
                try {
                    $deletedObject = $this->deleteOrphan($csrf, $storage, $database);
                } catch (OrphanObjectCleanupException|InvalidArgumentException $exception) {
                    $actionError = '객체를 삭제하지 않았습니다: ' . $exception->getMessage();
                } catch (Throwable $exception) {
                    error_log('Storage cleanup failed: ' . $exception->getMessage());
                    $actionError = '저장소 정리 작업에 실패했습니다. 서버 오류 로그를 확인해 주세요.';
                } finally {
                    $token = $csrf->issue($this->session, self::CLEANUP_TOKEN);
                }
            }

            $report = (new FileIntegrityScanner(
                new PdoFileIntegrityRepository($database),
                new FileObjectResolver($storage),
            ))->scan($offset, self::PAGE_SIZE);
            $total = $report->total;

            foreach ($report->items as $item) {
                $items[] = [
                    'document_id' => bin2hex($item->file->documentId),
                    'title' => $item->file->fullTitle(),
                    'sha256' => bin2hex($item->file->sha256),
                    'state' => $item->object->state->value,
                    'state_label' => $this->stateLabel($item->object->state),
                    'object_key' => $item->object->key->value,
                ];
            }

            $orphanReport = (new OrphanObjectScanner(
                $storage,
                new PdoObjectReferenceRepository($database),
            ))->scan($this->objectCursor(), self::PAGE_SIZE, time());
            $orphanScanned = $orphanReport->scanned;
            $ignoredRecent = $orphanReport->ignoredRecent;
            $ignoredUnmanaged = $orphanReport->ignoredUnmanaged;
            $nextObjectCursor = $orphanReport->nextCursor?->value;
            foreach ($orphanReport->candidates as $candidate) {
                $orphanItems[] = [
                    'object_key' => $candidate->object->key->value,
                    'last_modified' => date('Y-m-d H:i:s', $candidate->object->lastModified),
                    'last_modified_epoch' => $candidate->object->lastModified,
                    'age_hours' => intdiv($candidate->ageSeconds, 3600),
                    'size' => $candidate->object->size,
                ];
            }
        } catch (Throwable $exception) {
            $error = '저장소 무결성 검사에 실패했습니다: ' . $exception->getMessage();
        }

        return [
            'view_name' => 'storage_integrity',
            'title' => '파일 저장소 무결성',
            'data' => [
                'items' => $items,
                'total' => $total,
                'offset' => $offset,
                'limit' => self::PAGE_SIZE,
                'previous_offset' => $offset > 0 ? max(0, $offset - self::PAGE_SIZE) : null,
                'next_offset' => $offset + self::PAGE_SIZE < $total ? $offset + self::PAGE_SIZE : null,
                'orphan_items' => $orphanItems,
                'orphan_scanned' => $orphanScanned,
                'ignored_recent' => $ignoredRecent,
                'ignored_unmanaged' => $ignoredUnmanaged,
                'next_object_cursor' => $nextObjectCursor,
                'cleanup_token' => $token,
                'action_error' => $actionError,
                'deleted_object' => $deletedObject,
                'error' => $error,
            ],
            'menus' => [],
            'customData' => [],
        ];
    }

    private function deleteOrphan(
        CsrfTokenManager $csrf,
        ObjectStorageInterface $storage,
        PDO $database,
    ): string {
        if (!$csrf->validate($this->session, self::CLEANUP_TOKEN, $this->request->postScalarString('token'))) {
            throw new InvalidArgumentException('보안 토큰이 일치하지 않습니다. 페이지를 새로 고쳐 주세요.');
        }

        // A valid token is single-use even when later safety checks reject the request.
        $csrf->consume($this->session, self::CLEANUP_TOKEN);
        $csrf->issue($this->session, self::CLEANUP_TOKEN);

        $keyValue = $this->request->postScalarString('delete_key');
        $confirmation = $this->request->postScalarString('confirm_key');
        $lastModifiedValue = $this->request->postScalarString('last_modified');
        if (
            $keyValue === null
            || $confirmation === null
            || strlen($keyValue) > 80
            || strlen($confirmation) > 80
            || !hash_equals($keyValue, $confirmation)
            || $lastModifiedValue === null
            || strlen($lastModifiedValue) > 10
            || !ctype_digit($lastModifiedValue)
        ) {
            throw new InvalidArgumentException('객체 키를 정확히 다시 입력하고 유효한 검사 결과를 제출해 주세요.');
        }

        $actor = $this->session['uuid'] ?? null;
        if (!is_string($actor)) {
            throw new InvalidArgumentException('삭제 작업의 관리자 계정을 확인할 수 없습니다.');
        }

        $key = new ObjectKey($keyValue);
        (new OrphanObjectCleaner(
            $storage,
            new PdoObjectReferenceRepository($database),
            new PdoObjectMutationLock($database),
            new JsonLineAuditLogger(ProjectPaths::variable('log/storage-maintenance.jsonl')),
        ))->delete($key, (int) $lastModifiedValue, time(), $actor);

        return $key->value;
    }

    private function canDiagnoseStorage(): bool
    {
        $member = $this->session['member'] ?? null;
        $uuid = $this->session['uuid'] ?? null;
        if (!is_array($member) || !is_string($uuid)) {
            return false;
        }
        $username = $member['username'] ?? null;
        if (!is_string($username)) {
            return false;
        }

        $permissions = [];
        ACL::getAccountPerms($uuid, $username, $permissions);

        return in_array('config', $permissions, true) || in_array('developer', $permissions, true);
    }

    private function offset(): int
    {
        $value = $this->request->queryString('offset', '0');

        return ctype_digit($value) ? min((int) $value, PHP_INT_MAX - self::PAGE_SIZE) : 0;
    }

    private function stateLabel(FileObjectState $state): string
    {
        return match ($state) {
            FileObjectState::Current => '정상',
            FileObjectState::Legacy => '레거시 WebP',
            FileObjectState::Missing => '객체 누락',
        };
    }

    private function objectCursor(): ?ObjectKey
    {
        $value = $this->request->queryOptionalString('object_cursor');
        if ($value === null) {
            return null;
        }

        try {
            return new ObjectKey($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
