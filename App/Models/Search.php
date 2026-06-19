<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;

class Search extends \PressDo\App\Core\Model
{
    public static function updateIndex(string $uuid, string $text): void
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);

        try {
            $d = $db->prepare("UPDATE search_index SET `text`=? WHERE document=?");
            $d->execute([$text, $uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': failed to update search index');
        }
    }

    public static function softSearch(string $namespace, string $toplevel, string $midlevel, string $lowlevel): array
    {
        $db = self::db();

        try {
            $d = $db->prepare("SELECT `namespace`, `title` FROM document WHERE `namespace` = :ns AND (`title` REGEXP :q OR `title` REGEXP :c OR `title` REGEXP :r)
                ORDER BY CASE WHEN `title` REGEXP :q THEN 1 WHEN `title` REGEXP :c THEN 2 WHEN `title` REGEXP :r THEN 3 END, title ASC LIMIT 10");
            $d->execute(['ns' => $namespace, 'q' => $toplevel, 'c' => $midlevel, 'r' => $lowlevel]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': failed to search document titles');
        }

        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function hardSearch(string $keystring, string $target, ?string $namespace): array
    {
        $db = self::db();

        if (SqlDialect::isSqlite()) {
            $like = '%'.$keystring.'%';
            [$where, $args] = match ($target) {
                'title_content' => ['(s.`text` LIKE ? OR d.title = ?)', [$like, $keystring]],
                'title' => ['d.title = ?', [$keystring]],
                'content', 'raw' => ['s.`text` LIKE ?', [$like]],
                default => ['(s.`text` LIKE ? OR d.title = ?)', [$like, $keystring]],
            };
        } else {
            try {
                $db->query("SHOW VARIABLES LIKE 'innodb_ft_min_token_size'");
            } catch (PDOException $err) {
                throw new ErrorException($err->getMessage().': failed to inspect full-text configuration');
            }

            [$where, $args] = match ($target) {
                'title_content' => ['(MATCH(`text`) AGAINST(? IN BOOLEAN MODE) OR d.title = ?)', [$keystring, $keystring]],
                'title' => ['d.title = ?', [$keystring]],
                'content', 'raw' => ['MATCH(`text`) AGAINST(? IN BOOLEAN MODE)', [$keystring]],
                default => ['(MATCH(`text`) AGAINST(? IN BOOLEAN MODE) OR d.title = ?)', [$keystring, $keystring]],
            };
        }

        $sql = "SELECT d.namespace, d.title, s.text FROM search_index s JOIN document d ON d.uuid = s.document WHERE $where";
        if (!empty($namespace)) {
            $sql .= " AND d.namespace = ?";
            $args[] = $namespace;
        }

        try {
            $c = $db->prepare($sql);
            $c->execute($args);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': failed to execute search');
        }

        return $c->fetchAll(PDO::FETCH_ASSOC);
    }
}
