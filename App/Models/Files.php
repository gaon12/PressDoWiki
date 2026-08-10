<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Services\Document\DocumentRevision;
use PressDo\App\Services\File\FileMetadata;
use PressDo\App\Services\File\PdoFileDocumentStore;

class Files extends \PressDo\App\Core\Model
{
    /** Create the file page, initial revision, and metadata in one transaction. */
    public static function createDocument(
        string $namespace,
        string $title,
        string $content,
        string $comment,
        ?string $cont_m,
        ?string $cont_i,
        string $sha256,
        int $width,
        int $height,
    ): string {
        $db = self::db();
        $documentId = self::uuid2bin(self::generateUuid());
        $digest = hex2bin($sha256);
        if ($digest === false) {
            throw new \InvalidArgumentException('The file SHA-256 digest must be hexadecimal.');
        }

        if($cont_m !== null)
            $cont_m = self::uuid2bin($cont_m);
        elseif($cont_i !== null)
            $cont_i = self::uuid2bin($cont_i);

        try {
            (new PdoFileDocumentStore($db))->create(
                $namespace,
                $title,
                new DocumentRevision(
                    revisionId: self::uuid2bin(self::generateUuid()),
                    documentId: $documentId,
                    content: $content,
                    comment: $comment,
                    action: 'create',
                    revision: 1,
                    lengthDelta: mb_strlen($content, 'UTF-8'),
                    contributorMemberId: $cont_m,
                    contributorIpId: $cont_i,
                ),
                new FileMetadata($documentId, $digest, $width, $height),
            );
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 파일 문서 저장 중 오류 발생', previous: $err);
        }

        return self::bin2uuid($documentId);
    }

    public static function load(string $fileuuid): array
    {
        $db = self::db();
        $uuid = self::uuid2bin($fileuuid);

        try {
            $g = $db->prepare("SELECT `hash`, width, height FROM `files` WHERE uuid=?");
            $g->execute([$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 파일 데이터 저장 중 오류 발생');
        }
        $dataset = $g->fetch(PDO::FETCH_ASSOC);
        $dataset['hash'] = bin2hex($dataset['hash']);
        return $dataset;
    }

    /**
     * 파일 해시가 이미 존재하는지 확인
     * @param string $hash
     * @throws \ErrorException
     * @return bool
     */
    public static function findHash(string $hash): bool
    {
        $db = self::db();
        $H = hex2bin($hash);

        try {
            $g = $db->prepare("SELECT 1 FROM `files` WHERE `hash`=?");
            $g->execute([$H]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 파일 해시 조회 중 오류 발생');
        }
        return $g->fetchColumn() !== false;
    }
}
