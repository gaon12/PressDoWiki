<?php
namespace PressDo\App\Helpers;

use ErrorException;
use \PDO as PDO;

class Database
{
    private static ?PDO $instance = null;
    private static string $driver = '';

    /**
     * get database instance
     */
    public static function getInstance(): PDO
    {
        if(!self::$instance) {
            self::$instance = self::init();
        }
        return self::$instance;
    }

    public static function getDriver(): string
    {
        if (self::$driver === '') {
            self::$driver = strtolower((string) DefaultConfig::get('database.type'));
        }

        return self::$driver;
    }

    /**
     * Initialize database.
     */
    private static function init(): PDO
    {
        if(!self::$instance) {
            $type = strtolower((string) DefaultConfig::get('database.type'));
            self::$driver = $type;

            switch($type){
                case 'mysql':
                case 'cubrid':
                    $dsn = $type.':dbname='.DefaultConfig::get('database.name').';host='.DefaultConfig::get('database.host').';port='.DefaultConfig::get('database.port').';charset=utf8';
                    break;
                case 'pgsql':
                    if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
                        throw new ErrorException('PostgreSQL database type requires the pdo_pgsql PHP extension.');
                    }
                    $dsn = 'pgsql:dbname='.DefaultConfig::get('database.name').';host='.DefaultConfig::get('database.host').';port='.DefaultConfig::get('database.port');
                    break;
                case 'oracle':
                    $dsn = 'oci:dbname='.DefaultConfig::get('database.host').'/'.DefaultConfig::get('database.name').';charset=utf8';
                    break;
                case 'mssql':
                    $dsn = 'dblib:dbname='.DefaultConfig::get('database.name').';host='.DefaultConfig::get('database.host').';port='.DefaultConfig::get('database.port').';charset=utf8';
                    break;
                case 'firebird':
                    $dsn = 'firebird:dbname='.DefaultConfig::get('database.host').':'.DefaultConfig::get('database.name').';charset=utf8';
                    break;
                case 'db2':
                    $dsn = 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE='.DefaultConfig::get('database.name').';HOSTNAME='.DefaultConfig::get('database.host').';PORT='.DefaultConfig::get('database.port').';PROTOCOL=TCPIP;UID='.DefaultConfig::get('database.user').';PWD='.DefaultConfig::get('database.password');
                    break;
                case 'sqlite':
                    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                        throw new ErrorException('SQLite database type requires the pdo_sqlite PHP extension.');
                    }
                    $dsn = 'sqlite:'.self::sqlitePath((string) DefaultConfig::get('database.name'));
                    break;
                default:
                    throw new ErrorException('Unsupported database type: '.$type);
            }

            self::$instance = $type !== 'db2'
                ? new PDO($dsn, (string) DefaultConfig::get('database.user'), (string) DefaultConfig::get('database.password'))
                : new PDO($dsn, '', '');
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if ($type === 'sqlite') {
                self::configureSqlite(self::$instance);
            } elseif ($type === 'pgsql') {
                self::configurePostgresql(self::$instance);
            }
            
            if (!self::$instance) {
                throw new ErrorException('Cannot connect to database.');
            }
        }
        return self::$instance;
    }

    private static function sqlitePath(string $configuredPath): string
    {
        $path = trim($configuredPath);
        if ($path === '') {
            $path = 'data/pressdo.sqlite';
        }

        if ($path === ':memory:' || preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private static function configureSqlite(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        $pdo->sqliteCreateFunction('regexp', static function (?string $pattern, ?string $value): int {
            if ($pattern === null || $value === null) {
                return 0;
            }

            $regex = '/'.str_replace('/', '\/', $pattern).'/u';
            $result = @preg_match($regex, $value);

            return $result === 1 ? 1 : 0;
        }, 2);

        $pdo->sqliteCreateFunction('md5', static fn (?string $value): string => md5((string) $value), 1);
        $pdo->sqliteCreateFunction('char_length', static fn (?string $value): int => mb_strlen((string) $value), 1);
        $pdo->sqliteCreateFunction('unix_timestamp', static function (?string $value = null): int {
            if ($value === null || $value === '') {
                return time();
            }

            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : $timestamp;
        }, -1);
    }

    private static function configurePostgresql(PDO $pdo): void
    {
        $pdo->exec("SET client_encoding TO 'UTF8'");
    }
}
