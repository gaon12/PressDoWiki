<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Services\Document\DocumentConflictException;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentContentStore;
use RuntimeException;
use Throwable;

class EditRequest extends \PressDo\App\Core\Model
{
    /**
     * Create a new edit request.
     * @param string $slug
     * @param string $uuid
     * @param string $content
     * @param string $comment
     * @param mixed $cont_m
     * @param mixed $cont_i
     * @param int $rv
     * @param int $lengthDelta
     * @throws \ErrorException
     * @return void
     */
    public static function create(string $slug, string $uuid, string $content, string $comment, ?string $cont_m, ?string $cont_i, int $rv, int $lengthDelta): void
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        try {
            $e = $db->prepare('INSERT INTO editrequest (urlstr, document, comment, content, contributor_m, contributor_i, baserev, count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $e->bindValue(1, $slug);
            SqlDialect::bindBinary($db, $e, 2, $uuid);
            $e->bindValue(3, $comment);
            $e->bindValue(4, $content);
            SqlDialect::bindBinary($db, $e, 5, $cont_m);
            SqlDialect::bindBinary($db, $e, 6, $cont_i);
            $e->bindValue(7, $rv, PDO::PARAM_INT);
            $e->bindValue(8, $lengthDelta, PDO::PARAM_INT);
            $e->execute();
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 생성 중 오류 발생');
        }
    }
    
    /**
     * Get slug list of edit requests in given document.
     * @param string $uuid
     * @param mixed $mode
     * @throws \ErrorException
     * @return array
     */
    public static function getBulk(string $uuid, $mode='open'): array
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        try {
            $status = $mode === 'close' ? "status IN ('close', 'locked')" : "status = 'open'";
            $d = $db->prepare('SELECT urlstr FROM editrequest WHERE document = ? AND ' . $status);
            SqlDialect::bindBinary($db, $d, 1, $uuid);
            $d->execute();
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 목록 조회 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get data about given edit request.
     * @param string $slug
     * @throws \ErrorException
     */
    public static function get(string $slug): ?array
    {
        $db = self::db();
        
        try {
            $d = $db->prepare("SELECT d.namespace, d.title, document, er.status, count, comment, content, contributor_m, contributor_i, baserev, datetime, lastedit, executor_m, executor_i, acceptrev, reason
                FROM editrequest er INNER JOIN document d ON d.uuid = er.document WHERE er.urlstr = ?");
            $d->execute([$slug]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 정보 조회 중 오류 발생');
        }
        $res = $d->fetch(PDO::FETCH_ASSOC);
        if ($res === false) {
            return null;
        }

        $res['document'] = self::bin2uuid(self::readBinary($res['document']));
        foreach (['contributor_m', 'contributor_i', 'executor_m', 'executor_i'] as $field) {
            $res[$field] = self::readBinary($res[$field]);
        }

        return $res;
    }
    
    /**
     * Modify given edit request.
     * @param string $slug
     * @param string $content
     * @param string $comment
     * @param int $newdifflen
     * @throws \ErrorException
     * @return void
     */
    public static function modify(string $slug, string $content, string $comment, int $newdifflen): void
    {
        $db = self::db();
        
        try {
            $d = $db->prepare("UPDATE editrequest SET content = ?, comment = ?, count = ?, lastedit = ? WHERE urlstr = ? AND status = 'open'");
            $d->execute([$content, $comment, $newdifflen, time(), $slug]);
            if ($d->rowCount() !== 1) {
                throw new DocumentConflictException('The edit request changed before it could be updated.');
            }
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 수정 중 오류 발생');
        }
    }
    
    public static function accept(string $slug, array $erdata, ?string $cont_m, ?string $cont_i): void
    {
        $db = self::db();
        $revisionId = self::uuid2bin(self::generateUuid());

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        $document = $erdata['document'] ?? null;
        $namespace = $erdata['namespace'] ?? null;
        $title = $erdata['title'] ?? null;
        $content = $erdata['content'] ?? null;
        $comment = $erdata['comment'] ?? null;
        $baseRevision = filter_var($erdata['baserev'] ?? null, FILTER_VALIDATE_INT);
        $lastEdit = filter_var($erdata['lastedit'] ?? null, FILTER_VALIDATE_INT);
        $lengthDelta = filter_var($erdata['count'] ?? null, FILTER_VALIDATE_INT);
        $contributorMember = $erdata['contributor_m'] ?? null;
        $contributorIp = $erdata['contributor_i'] ?? null;

        if (
            !is_string($document)
            || !is_string($namespace)
            || !is_string($title)
            || !is_string($content)
            || ($comment !== null && !is_string($comment))
            || $baseRevision === false
            || $baseRevision < 1
            || $lastEdit === false
            || $lengthDelta === false
            || ($contributorMember !== null && !is_string($contributorMember))
            || ($contributorIp !== null && !is_string($contributorIp))
        ) {
            throw new RuntimeException('편집 요청 리비전 데이터가 올바르지 않습니다.');
        }

        $startedTransaction = !$db->inTransaction();
        if ($startedTransaction) {
            $db->beginTransaction();
        } else {
            $db->exec('SAVEPOINT accept_edit_request');
        }

        try {
            $nextRevision = $baseRevision + 1;
            $commentCondition = $comment === null ? 'comment IS NULL' : SqlDialect::caseSensitiveEquals('comment');
            $update = $db->prepare("UPDATE editrequest SET status = 'accepted', acceptrev = ?, lastedit = ?, executor_m = ?, executor_i = ?
                WHERE urlstr = ? AND document = ? AND status = 'open' AND baserev = ? AND lastedit = ? AND count = ? AND "
                . SqlDialect::caseSensitiveEquals('content') . ' AND ' . $commentCondition);
            $update->bindValue(1, $nextRevision, PDO::PARAM_INT);
            $update->bindValue(2, time(), PDO::PARAM_INT);
            SqlDialect::bindBinary($db, $update, 3, $cont_m);
            SqlDialect::bindBinary($db, $update, 4, $cont_i);
            $update->bindValue(5, $slug);
            SqlDialect::bindBinary($db, $update, 6, self::uuid2bin($document));
            $update->bindValue(7, $baseRevision, PDO::PARAM_INT);
            $update->bindValue(8, $lastEdit, PDO::PARAM_INT);
            $update->bindValue(9, $lengthDelta, PDO::PARAM_INT);
            $update->bindValue(10, $content);
            if ($comment !== null) {
                $update->bindValue(11, $comment);
            }
            $update->execute();
            if ($update->rowCount() !== 1) {
                throw new DocumentConflictException('The edit request changed before it could be accepted.');
            }

            (new PdoDocumentContentStore($db))->edit($namespace, $title, $baseRevision, new DocumentRevision(
                revisionId: $revisionId,
                documentId: self::uuid2bin($document),
                content: $content,
                comment: $comment ?? '',
                action: 'modify',
                revision: $nextRevision,
                lengthDelta: $lengthDelta,
                contributorMemberId: $contributorMember,
                contributorIpId: $contributorIp,
                editRequestSlug: $slug,
            ));

            if ($startedTransaction) {
                $db->commit();
            } else {
                $db->exec('RELEASE SAVEPOINT accept_edit_request');
            }
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                if ($startedTransaction) {
                    $db->rollBack();
                } else {
                    $db->exec('ROLLBACK TO SAVEPOINT accept_edit_request');
                    $db->exec('RELEASE SAVEPOINT accept_edit_request');
                }
            }

            if ($error instanceof PDOException) {
                throw new ErrorException($error->getMessage().': 편집 요청 승인 중 오류 발생', previous: $error);
            }

            throw $error;
        }
    }
    
    /**
     * Close or lock given edit request.
     * @param string $slug
     * @param string $reason
     * @param mixed $cont_m
     * @param mixed $cont_i
     * @param bool $lock        edit request will be locked if true
     * @throws \ErrorException
     * @return void
     */
    public static function close(string $slug, string $reason, ?string $cont_m, ?string $cont_i, bool $lock=false): void
    {
        $db = self::db();

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        $status = $lock ? 'locked' : 'close';
        
        try {
            $d = $db->prepare("UPDATE editrequest SET status = ?, lastedit = ?, executor_m = ?, executor_i = ?, reason = ? WHERE urlstr = ? AND status = 'open'");
            $d->bindValue(1, $status);
            $d->bindValue(2, time(), PDO::PARAM_INT);
            SqlDialect::bindBinary($db, $d, 3, $cont_m);
            SqlDialect::bindBinary($db, $d, 4, $cont_i);
            $d->bindValue(5, $reason);
            $d->bindValue(6, $slug);
            $d->execute();
            if ($d->rowCount() !== 1) {
                throw new DocumentConflictException('The edit request changed before it could be closed.');
            }
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 닫기기 중 오류 발생');
        }
    }

    public static function reopen(string $slug, string $expectedStatus = 'close'): void
    {
        if (!in_array($expectedStatus, ['close', 'locked'], true)) {
            throw new DocumentConflictException('Only a closed or locked edit request can be reopened.');
        }

        $db = self::db();
        
        try {
            $d = $db->prepare("UPDATE editrequest SET status = 'open', lastedit = ? WHERE urlstr = ? AND status = ?");
            $d->execute([time(), $slug, $expectedStatus]);
            if ($d->rowCount() !== 1) {
                throw new DocumentConflictException('The edit request changed before it could be reopened.');
            }
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 편집 요청 열기기 중 오류 발생');
        }
    }
}
