<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

use Closure;
use PDO;
use PDOStatement;
use PressDo\App\Helpers\SqlDialect;
use RuntimeException;
use Throwable;

final readonly class EmailVerification
{
    private const RESEND_INTERVAL = 60;

    public function __construct(private PDO $database) {}

    public function issue(string $email, string $ip, EmailVerificationPurpose $purpose): ?string
    {
        $address = inet_pton($ip);
        if ($address === false) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $sql = 'INSERT INTO email_keys (email, ip, `key`, `time`) VALUES (?, ?, ?, ?)';
        $mysql = SqlDialect::isMysql();
        $sql .= $mysql
            ? ' ON DUPLICATE KEY UPDATE
                ip = IF(`time` <= ?, VALUES(ip), ip),
                `key` = IF(`time` <= ?, VALUES(`key`), `key`),
                `time` = GREATEST(`time`, IF(`time` <= ?, VALUES(`time`), `time`))'
            : ' ON CONFLICT (email) DO UPDATE SET ip = excluded.ip, `key` = excluded.`key`, `time` = excluded.`time`
                WHERE email_keys.`time` <= ?';
        $now = time();
        $cutoff = $now - self::RESEND_INTERVAL;
        $statement = $this->prepare($sql);
        $statement->bindValue(1, $email);
        $statement->bindValue(2, $address, PDO::PARAM_LOB);
        $statement->bindValue(3, self::digest($token, $purpose), PDO::PARAM_LOB);
        $statement->bindValue(4, $now, PDO::PARAM_INT);
        $statement->bindValue(5, $cutoff, PDO::PARAM_INT);
        if ($mysql) {
            $statement->bindValue(6, $cutoff, PDO::PARAM_INT);
            $statement->bindValue(7, $cutoff, PDO::PARAM_INT);
        }
        $statement->execute();

        return $statement->rowCount() === 0 ? null : $token;
    }

    public function verify(string $token, EmailVerificationPurpose $purpose, string $ip = ''): ?string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
            return null;
        }

        $sql = 'SELECT email FROM email_keys WHERE `key` = ? AND `time` >= ?';
        $address = null;
        if ($purpose === EmailVerificationPurpose::Signup) {
            $address = inet_pton($ip);
            if ($address === false) {
                return null;
            }
            $sql .= ' AND ip = ?';
        }

        $statement = $this->prepare($sql);
        $statement->bindValue(1, self::digest($token, $purpose), PDO::PARAM_LOB);
        $statement->bindValue(2, time() - $purpose->lifetime(), PDO::PARAM_INT);
        if ($address !== null) {
            $statement->bindValue(3, $address, PDO::PARAM_LOB);
        }
        $statement->execute();
        $email = $statement->fetchColumn();

        return is_string($email) ? $email : null;
    }

    /** @param Closure(string): bool $apply */
    public function consume(string $token, EmailVerificationPurpose $purpose, Closure $apply, string $ip = ''): bool
    {
        if (!$this->database->beginTransaction()) {
            throw new RuntimeException('Unable to start email verification transaction.');
        }

        try {
            $email = $this->verify($token, $purpose, $ip);
            if ($email === null) {
                $this->database->rollBack();
                return false;
            }

            $statement = $this->prepare('DELETE FROM email_keys WHERE email = ? AND `key` = ? AND `time` >= ?');
            $statement->bindValue(1, $email);
            $statement->bindValue(2, self::digest($token, $purpose), PDO::PARAM_LOB);
            $statement->bindValue(3, time() - $purpose->lifetime(), PDO::PARAM_INT);
            $statement->execute();
            if ($statement->rowCount() !== 1 || !$apply($email)) {
                $this->database->rollBack();
                return false;
            }

            return $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    private function prepare(string $sql): PDOStatement
    {
        $statement = $this->database->prepare(SqlDialect::normalizeIdentifiers($sql));
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare email verification statement.');
        }

        return $statement;
    }

    private static function digest(string $token, EmailVerificationPurpose $purpose): string
    {
        return hash('sha512', $purpose->value . ':' . $token, true);
    }
}
