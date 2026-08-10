<?php
namespace PressDo\App\Models\Concerns;

use ErrorException;
use PDO;
use PDOException;
use PressDo\App\Helpers\SqlDialect;

trait DocumentPageLists
{
    /**
     * Get list of random documents
     * @param string $namespace
     * @param int $quantity
     * @throws ErrorException
     * @return array
     */
    public static function getRandom(string $namespace='문서', int $quantity=1): array
    {
        $db = self::db();
        try {
            $quantity = max(1, min(100, $quantity));
            $d = $db->prepare("SELECT `namespace`,`title` FROM `document` WHERE `namespace`=? ORDER BY ".SqlDialect::randomOrder()." LIMIT ".$quantity);
            $d->execute([$namespace]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 무작위 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getOldestPages(int $from, int $count): array
    {
        $db = self::db();
        --$from;
        ++$count;
        
        try {
            $limit = SqlDialect::limit($from, $count);
            $d = $db->query("SELECT d.namespace, d.title, MAX(h.`datetime`) as dt FROM `history` as h INNER JOIN `document` as d ON d.uuid = h.document
                WHERE NOT EXISTS (SELECT 1 FROM links as l WHERE l.from_uuid = h.document AND l.type = 'redirect') AND d.namespace != '사용자' GROUP BY h.`document` ORDER BY dt ASC $limit");
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 오래된 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getPagesByLength(string $order, int $from, int $count): array
    {
        $db = self::db();
        --$from;
        ++$count;
        
        $order = SqlDialect::orderDirection($order, 'DESC');
        $limit = SqlDialect::limit($from, $count);
        $sql = "SELECT d.namespace, d.title, len FROM
            (SELECT document, `datetime`, CHAR_LENGTH(`content`) as len,
                RANK() OVER (PARTITION BY document ORDER BY `datetime` DESC) AS rnk FROM `history`
            ) AS h INNER JOIN `document` as d ON d.uuid = document WHERE d.namespace = '문서' AND rnk = 1 AND NOT EXISTS
            (SELECT 1 FROM links as l WHERE l.from_uuid = document AND l.type = 'redirect') GROUP BY `document` ORDER BY len $order $limit";
        try {
            $d = $db->query($sql);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 길이순으로 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getNeededPages(string $namespace, int $from, int $count): array
    {
        $db = self::db();
        --$from;
        ++$count;
        try {
            $limit = SqlDialect::limit($from, $count);
            $d = $db->prepare("SELECT l.namespace, l.title FROM `links` as l WHERE l.namespace = ? AND NOT EXISTS (SELECT 1 FROM document as d WHERE d.namespace = l.namespace AND d.title = l.title) $limit");
            $d->execute([$namespace]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 작성이 필요한 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getUncategorizedPages(string $namespace, int $from, int $count): array
    {
        $db = self::db();
        --$from;
        ++$count;
        try {
            $limit = SqlDialect::limit($from, $count);
            $d = $db->prepare("SELECT d.namespace, d.title FROM document as d WHERE d.namespace = ? AND NOT EXISTS (SELECT 1 FROM links as l WHERE d.uuid = l.from_uuid AND l.type = 'category') ORDER BY d.title ASC $limit");
            $d->execute([$namespace]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 분류되지 않은 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getOrphanedPages(string $namespace, string $fpuuid, int $from, int $count): array
    {
        $db = self::db();
        --$from;
        ++$count;
        try {
            $limit = SqlDialect::limit($from, $count);
            $d = $db->prepare("SELECT d.namespace, d.title FROM document as d WHERE d.namespace = ? AND NOT EXISTS (SELECT 1 FROM links as l WHERE d.uuid = l.from_uuid AND l.type = 'category') ORDER BY d.title ASC $limit");
            $d->execute([$namespace]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 분류되지 않은 문서 불러오기 중 오류 발생');
        }
        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{
     *     License: list<array{title: string}>,
     *     Category: list<array{title: string}>
     * }
     */
    public static function getLicensesAndCategories(): array
    {
        $db = self::db();
        try {
            $d = $db->query("SELECT title FROM document WHERE `namespace` = '틀' AND title LIKE '이미지 라이선스/%' AND title != '이미지 라이선스/'");
            $license = $d->fetchAll(PDO::FETCH_ASSOC);
            $d = $db->query("SELECT title FROM document WHERE `namespace` = '분류' AND title LIKE '파일/%' AND title != '파일/'");
            $category = $d->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 문서명 검색 중 오류 발생');
        }
        return ['License' => $license, 'Category' => $category];
    }
}
