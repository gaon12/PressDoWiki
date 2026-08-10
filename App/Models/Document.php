<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Core\Controller;
use PressDo\App\Models\Concerns\DocumentPageLists;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\Document\PdoDocumentDeletionStore;
use PressDo\App\Services\Document\PdoDocumentRevisionStore;

class Document extends \PressDo\App\Core\Model
{
    use DocumentPageLists;

    /**
     * Create new document
     * @param string $namespace
     * @param string $title
     * @throws ErrorException
     * @return string UUID of created document
     */
    public static function create(string $namespace, string $title): string
    {
        $db = self::db();
        $uuid = self::uuid2bin(self::generateUuid());

        try {
            $d = $db->prepare("INSERT INTO `document`(uuid,namespace,title) VALUES(?,?,?)");
            $d->execute([$uuid, $namespace, $title]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 생성 중 오류 발생');
        }
        
        return self::bin2uuid($uuid);
    }

    public static function recreate(string $uuid): void
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);

        try {
            $d = $db->prepare("UPDATE `document` SET `status`='normal' WHERE uuid=?");
            $d->execute([$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 재생성 중 오류 발생');
        }
    }

    /**
     * Load document data
     * @param string $uuid     uuid of document
     * @param ?string $rev     revision uuid or number of document
     * @throws ErrorException
     * @return ?array       array (history data)
     */
    public static function load(string $uuid, string|int|null $rev=null): ?array
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        $sql = "SELECT h.uuid,h.content,h.comment,h.datetime,h.action,h.rev,h.count,h.reverted_version,h.contributor_m,h.contributor_i, h.edit_request_uri,h.acl_changed,h.moved_from,h.moved_to,h.revstatus, d.status
        FROM `history` as h INNER JOIN `document` as d ON d.uuid = h.document WHERE h.`document`=?";

        if ($rev === null) {
            $sql .= " ORDER BY h.`datetime` DESC LIMIT 1";
            $param = [$uuid];
        } else {
            if (is_numeric($rev)) {
                $sql .= " AND h.rev=?";
            } else {
                $rev = self::uuid2bin($rev);
                $sql .= " AND h.uuid=?";
            }
            $param = [$uuid, $rev];
        }
        
        $d = $db->prepare($sql);

        try {
            $d->execute($param);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 데이터 조회 중 오류 발생');
        }

        $res = $d->fetch(PDO::FETCH_ASSOC);
        if ($res === false)
            $res = null;
        else {
            if ($res['content'] == null && (int) $res['rev'] !== 1 && $res['status'] !== 'delete') {
                $q = $db->prepare("SELECT content FROM history WHERE content IS NOT NULL AND document=? AND rev < ? ORDER BY `datetime` DESC LIMIT 1");
                try {
                    $q->execute([$uuid, $res['rev']]);
                } catch (PDOException $err) {
                    throw new ErrorException($err->getMessage().': 문서 본문 조회 중 오류 발생');
                }

                $previous = $q->fetch(PDO::FETCH_ASSOC);
                if ($previous !== false)
                    $res['content'] = $previous['content'];
            }
            $res['uuid'] = self::bin2uuid($res['uuid']);
        }

        # return null if not found
        return $res;
    }

    /**
     * Save edited Document
     * @param string $uuid
     * @param string $content
     * @param string $comment
     * @param ?string $cont_m
     * @param ?string $cont_i
     * @param int $baserev
     * @param int $prevlen
     * @param string $action
     * @throws ErrorException
     * @return void
     */
    public static function save(string $uuid, string $content, string $comment, ?string $cont_m, ?string $cont_i, int $baserev, int $prevlen, string $action): void
    {
        $db = self::db();
        $lengthDelta = mb_strlen($content, 'UTF-8') - $prevlen;
        
        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        $documentId = self::uuid2bin($uuid);

        try {
            (new PdoDocumentRevisionStore($db))->append(new DocumentRevision(
                revisionId: self::uuid2bin(self::generateUuid()),
                documentId: $documentId,
                content: $content,
                comment: $comment,
                action: $action,
                revision: $baserev + 1,
                lengthDelta: $lengthDelta,
                contributorMemberId: $cont_m,
                contributorIpId: $cont_i,
            ));
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 편집 저장 중 오류 발생', previous: $err);
        }
    }

    /**
     * Move document
     * @param string $uuid
     * @param string $from
     * @param string $to
     * @param ?string $cont_m
     * @param ?string $cont_i
     * @param string $comment
     * @throws ErrorException
     * @return void
     */
    public static function move(string $uuid, string $from, string $to, ?string $cont_m, ?string $cont_i, int $baserev, string $comment): void
    {
        $db = self::db();

        [$toNS, $toT] = Controller::parseTitle($to);
        [$fromNS, $fromT] = Controller::parseTitle($from);

        $uuid = self::uuid2bin($uuid);

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        $a = $db->prepare("UPDATE `document` SET `namespace`=?, `title`=? WHERE `uuid`=?");
        $a->execute([$toNS, $toT, $uuid]);

        $d = [
            self::uuid2bin(self::generateUuid()),
            $uuid,
            $comment,
            'move',
            $baserev + 1,
            0,
            $cont_m,
            $cont_i,
            $from,
            $to
        ];
        
        try {
            $b = $db->prepare("INSERT INTO `history`(uuid,document,comment,action,rev,count,contributor_m,contributor_i,moved_from,moved_to) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $b->execute($d);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 이동 중 오류 발생');
        }
    }

    public static function delete(string $uuid, ?string $cont_m, ?string $cont_i, int $length, int $baserev, string $comment): void
    {
        $db = self::db();
        $documentId = self::uuid2bin($uuid);

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        try {
            (new PdoDocumentDeletionStore($db))->delete(new DocumentRevision(
                revisionId: self::uuid2bin(self::generateUuid()),
                documentId: $documentId,
                content: null,
                comment: $comment,
                action: 'delete',
                revision: $baserev + 1,
                lengthDelta: -$length,
                contributorMemberId: $cont_m,
                contributorIpId: $cont_i,
            ));
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 삭제 중 오류 발생', previous: $err);
        }
    }

    /**
     * Get UUID of the document.
     * @param   string $namespace     Document namespace (raw)
     * @param   string $title     Document Title
     * @return  int|bool       Document ID (false if not exist)
     */
    public static function getUuid($namespace, $title, &$backlinkrefreshed = false): string|bool
    {
        $db = self::db();

        try {
            $c = $db->prepare("SELECT uuid, backlink_updated FROM `document` WHERE `namespace`=? AND ".SqlDialect::caseSensitiveEquals('`title`'));
            $c->execute([$namespace, $title]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서 UUID 조회 중 오류 발생');
        }

        $f = $c->fetch(PDO::FETCH_ASSOC);
        if ($f === false)
            return false;
        else {
            $backlinkrefreshed = boolval($f['backlink_updated']);
            return self::bin2uuid($f['uuid']);
        }
    }

    /**
     * Get title of the document by ID.
     *
     * @param   array $ids     Document BINARY UUID (least 1 doc)
     * @return  array       Document namespace, title
     */
    public static function getBulkTitle(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $db = self::db();
        try {
            $ORSTATEMENT = str_repeat(',?', count($ids) - 1);
            $c = $db->prepare("SELECT `uuid`,`namespace`,`title` FROM `document` WHERE `uuid` IN (?".$ORSTATEMENT.")");
            $c->execute($ids);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': ID로 문서명 조회 중 오류 발생');
        }

        $res = $c->fetchAll(PDO::FETCH_ASSOC);
        
        return $res;
    }

    /**
     * Get title of the document by UUID.
     *
     * @param   string $uuid     Document UUID
     * @return  array|false       Document namespace, title. returns false if not exist.
     */
    public static function getTitleByUuid(string $uuid): array|false
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        try {
            $c = $db->prepare("SELECT `namespace`,`title`, `status` FROM `document` WHERE `uuid`=?");
            $c->execute([$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': ID로 문서명 조회 중 오류 발생');
        }

        $res = $c->fetch(PDO::FETCH_ASSOC);
        $res = $res === false ? false : $res;
        return $res;
    }

}
