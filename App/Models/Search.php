<?php

declare(strict_types=1);

namespace PressDo\App\Models;

use ErrorException;
use PDO;
use PDOException;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Services\Search\PdoSearchIndex;
use UnexpectedValueException;

final class Search extends \PressDo\App\Core\Model
{
    public static function updateIndex(string $uuid, string $text): void
    {
        $binaryUuid = self::uuid2bin($uuid);

        try {
            (new PdoSearchIndex(self::db()))->put($binaryUuid, $text);
        } catch (PDOException $error) {
            throw new ErrorException($error->getMessage() . ': failed to update search index', previous: $error);
        }
    }

    /** @return list<array{namespace: string, title: string}> */
    public static function softSearch(string $namespace, string $toplevel, string $midlevel, string $lowlevel): array
    {
        $db = self::db();

        try {
            $statement = $db->prepare('SELECT `namespace`, `title` FROM document WHERE `namespace` = :ns AND (`title` REGEXP :q OR `title` REGEXP :c OR `title` REGEXP :r)
                ORDER BY CASE WHEN `title` REGEXP :q THEN 1 WHEN `title` REGEXP :c THEN 2 WHEN `title` REGEXP :r THEN 3 END, title ASC LIMIT 10');
            $statement->execute(['ns' => $namespace, 'q' => $toplevel, 'c' => $midlevel, 'r' => $lowlevel]);
        } catch (PDOException $error) {
            throw new ErrorException($error->getMessage() . ': failed to search document titles', previous: $error);
        }

        $results = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = self::searchRow($row, false);
        }

        return $results;
    }

    /** @return list<array{namespace: string, title: string, text: string}> */
    public static function hardSearch(string $keystring, string $target, ?string $namespace): array
    {
        $db = self::db();

        if (!SqlDialect::isMysql()) {
            $like = '%' . $keystring . '%';
            [$where, $args] = match ($target) {
                'title_content' => ['(s.`text` LIKE ? OR d.title = ?)', [$like, $keystring]],
                'title' => ['d.title = ?', [$keystring]],
                'content', 'raw' => ['s.`text` LIKE ?', [$like]],
                default => ['(s.`text` LIKE ? OR d.title = ?)', [$like, $keystring]],
            };
        } else {
            try {
                $db->query("SHOW VARIABLES LIKE 'innodb_ft_min_token_size'");
            } catch (PDOException $error) {
                throw new ErrorException($error->getMessage() . ': failed to inspect full-text configuration', previous: $error);
            }

            [$where, $args] = match ($target) {
                'title_content' => ['(MATCH(`text`) AGAINST(? IN BOOLEAN MODE) OR d.title = ?)', [$keystring, $keystring]],
                'title' => ['d.title = ?', [$keystring]],
                'content', 'raw' => ['MATCH(`text`) AGAINST(? IN BOOLEAN MODE)', [$keystring]],
                default => ['(MATCH(`text`) AGAINST(? IN BOOLEAN MODE) OR d.title = ?)', [$keystring, $keystring]],
            };
        }

        $sql = "SELECT d.namespace, d.title, s.text FROM search_index s JOIN document d ON d.uuid = s.document WHERE {$where}";
        if (!empty($namespace)) {
            $sql .= ' AND d.namespace = ?';
            $args[] = $namespace;
        }

        try {
            $statement = $db->prepare($sql);
            $statement->execute($args);
        } catch (PDOException $error) {
            throw new ErrorException($error->getMessage() . ': failed to execute search', previous: $error);
        }

        $results = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = self::searchRow($row, true);
        }

        return $results;
    }

    /**
     * @param mixed $row Database-provided row.
     * @return ($withText is true ? array{namespace: string, title: string, text: string} : array{namespace: string, title: string})
     */
    private static function searchRow(mixed $row, bool $withText): array
    {
        if (!is_array($row)) {
            throw new UnexpectedValueException('A search result row must be an array.');
        }

        $namespace = $row['namespace'] ?? null;
        $title = $row['title'] ?? null;
        if (!is_string($namespace) || !is_string($title)) {
            throw new UnexpectedValueException('A search result must contain a namespace and title.');
        }

        if (!$withText) {
            return ['namespace' => $namespace, 'title' => $title];
        }

        $text = $row['text'] ?? null;
        if (!is_string($text)) {
            throw new UnexpectedValueException('A full-text search result must contain indexed text.');
        }

        return ['namespace' => $namespace, 'title' => $title, 'text' => $text];
    }
}
