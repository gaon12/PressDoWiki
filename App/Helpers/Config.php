<?php

namespace PressDo\App\Helpers;

class Config
{
    /** @var array<string, mixed> */
    private static array $Configs = [];

    private static $db = null;

    /**
     * Connect Database.
     * @return \PDO
     */
    protected static function db(): \PDO
    {
        if (!self::$db) {
            self::$db = Database::getInstance();
            return self::$db;
        } else {
            return self::$db;
        }
    }

    /**
     * Initialize configs.
     */
    private static function init(): void
    {
        if (empty(static::$Configs)) {
            $instance = self::db();
            $d = $instance->query('SELECT `key`, `value` FROM config');
            $cset = array_merge(DefaultConfig::all(), $d->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN));
            $carray = [];
            foreach ($cset as $k => $c) {
                if (is_countable($c) && count($c) === 1) {
                    $carray[$k] = $c[0];
                } else {
                    $carray[$k] = $c;
                }
            }
            static::$Configs = $carray;
        }
    }

    /**
     * get config value
     */
    public static function get(string $key, string $implodeDelimiter = ''): mixed
    {
        self::init();
        $res = static::$Configs[$key];
        if (!empty($implodeDelimiter) && is_countable($res)) {
            $res = implode($implodeDelimiter, $res);
        }
        return $res;
    }

    /**
     * get All array
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        self::init();
        return static::$Configs;
    }

    public static function getPreference(): array
    {
        $instance = self::db();
        $d = $instance->query('SELECT `key`, `value` FROM config');
        return $d->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
    }

    public static function set(string $key, string $name): void
    {
        $db = self::db();
        $d = $db->prepare('INSERT INTO config (`key`, `value`) VALUES (?, ?)');
        $d->execute([$key, $name]);
        static::$Configs = [];
    }

    public static function delete(string $key, string $name): void
    {
        $db = self::db();
        $d = $db->prepare('DELETE FROM config WHERE `key`=? AND `value`=?');
        $d->execute([$key, $name]);
        static::$Configs = [];
    }

    public static function setBulk(array $data): void
    {
        if (count($data) % 2 !== 0) {
            throw new \InvalidArgumentException('Config bulk data must contain key/value pairs.');
        }

        $db = self::db();
        $str = implode(', ', array_fill(0, (int) (count($data) / 2), '(?, ?)'));

        try {
            $db->beginTransaction();
            $db->query('DELETE FROM config');

            if (!empty($data)) {
                $d = $db->prepare('INSERT INTO config (`key`, `value`) VALUES ' . $str);
                $d->execute($data);
            }

            $db->commit();
            static::$Configs = [];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Atomically replace the scalar values selected by the settings catalog.
     * Unrelated rows, including ACL configuration, are deliberately preserved.
     *
     * @param array<string, string> $values
     */
    public static function replaceValues(array $values): void
    {
        $db = self::db();
        $delete = $db->prepare('DELETE FROM config WHERE `key` = ?');
        $insert = $db->prepare('INSERT INTO config (`key`, `value`) VALUES (?, ?)');

        try {
            $db->beginTransaction();

            foreach ($values as $key => $value) {
                $delete->execute([$key]);
                $insert->execute([$key, $value]);
            }

            $db->commit();
            static::$Configs = [];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }
}
