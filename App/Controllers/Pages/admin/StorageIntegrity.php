<?php

declare(strict_types=1);

namespace PressDo\App\Controllers\Pages\admin;

use PressDo\App\Core\Controller;
use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\Database;
use PressDo\App\Helpers\Languages;
use PressDo\App\Models\ACL;
use PressDo\App\Services\File\FileIntegrityScanner;
use PressDo\App\Services\File\FileObjectResolver;
use PressDo\App\Services\File\FileObjectState;
use PressDo\App\Services\File\PdoFileIntegrityRepository;
use PressDo\App\Services\Uploaders\ObjectStorageFactory;
use Throwable;

final class StorageIntegrity extends Controller
{
    private const PAGE_SIZE = 25;

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

        try {
            $storageType = Config::get('storage.type');
            if (!is_string($storageType)) {
                throw new \RuntimeException('Object storage type is not configured.');
            }
            $storage = ObjectStorageFactory::create($storageType);
            $report = (new FileIntegrityScanner(
                new PdoFileIntegrityRepository(Database::getInstance()),
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
                'error' => $error,
            ],
            'menus' => [],
            'customData' => [],
        ];
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
}
