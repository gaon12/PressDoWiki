<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

use PDO;
use PressDo\App\Helpers\SqlDialect;
use RuntimeException;

final readonly class SessionRegistry
{
    public const REMEMBER = 'szczecin';
    public const TRUST = 'podgorica';
    private const IDLE_LIFETIME = 86_400;
    private const TOUCH_INTERVAL = 300;

    public function __construct(private PDO $database) {}

    public static function validId(string $id): bool
    {
        return strlen($id) === 32 && ctype_xdigit($id) && strtolower($id) === $id;
    }

    /**
     * @param array<string, int> $cookieLifetimes
     * @return array{id: string, cookies: array<string, string>}
     */
    public function create(string $member, string $userAgent, int $now, array $cookieLifetimes = []): array
    {
        $expired = $this->database->prepare('DELETE FROM member_sessions WHERE member_id = ? AND expires_at <= ?');
        SqlDialect::bindBinary($this->database, $expired, 1, $member);
        $expired->bindValue(2, $now, PDO::PARAM_INT);
        $expired->execute();

        $id = bin2hex(random_bytes(16));
        $cookies = [];
        foreach ([self::REMEMBER, self::TRUST] as $name) {
            if (isset($cookieLifetimes[$name])) {
                $cookies[$name] = bin2hex(random_bytes(32));
            }
        }

        $rememberExpiry = isset($cookies[self::REMEMBER]) ? $now + $cookieLifetimes[self::REMEMBER] : null;
        $trustExpiry = isset($cookies[self::TRUST]) ? $now + $cookieLifetimes[self::TRUST] : null;
        $statement = $this->database->prepare(
            'INSERT INTO member_sessions (member_id, id, user_agent, created_at, last_seen_at, expires_at,
                remember_digest, remember_expires_at, trust_digest, trust_expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        SqlDialect::bindBinary($this->database, $statement, 1, $member);
        $values = [
            $id,
            mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, 255, 'UTF-8'),
            $now,
            $now,
            max($now + self::IDLE_LIFETIME, $rememberExpiry ?? 0, $trustExpiry ?? 0),
            isset($cookies[self::REMEMBER]) ? hash('sha256', $cookies[self::REMEMBER]) : null,
            $rememberExpiry,
            isset($cookies[self::TRUST]) ? hash('sha256', $cookies[self::TRUST]) : null,
            $trustExpiry,
        ];
        foreach ($values as $index => $value) {
            $statement->bindValue($index + 2, $value);
        }
        $statement->execute();

        return ['id' => $id, 'cookies' => $cookies];
    }

    /** @return array{id: string, member: string}|null */
    public function findByCookie(string $name, string $value, int $now, bool $lock = false): ?array
    {
        if (strlen($value) !== 64 || !ctype_xdigit($value) || strtolower($value) !== $value) {
            return null;
        }
        [$digestColumn, $expiryColumn] = match ($name) {
            self::REMEMBER => ['remember_digest', 'remember_expires_at'],
            self::TRUST => ['trust_digest', 'trust_expires_at'],
            default => throw new RuntimeException('Unknown session cookie.'),
        };
        $suffix = $lock && $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite' ? ' FOR UPDATE' : '';
        $statement = $this->database->prepare(
            "SELECT id, member_id FROM member_sessions WHERE $digestColumn = ? AND $expiryColumn > ? AND expires_at > ?" . $suffix,
        );
        $statement->execute([hash('sha256', $value), $now, $now]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['id'] ?? null)) {
            return null;
        }
        $member = $row['member_id'] ?? null;
        if (is_resource($member)) {
            $member = stream_get_contents($member);
        }

        return is_string($member) ? ['id' => $row['id'], 'member' => $member] : null;
    }

    /** @return array<string, string> */
    public function renewRemember(string $member, string $id, ?int $lifetime, int $now): array
    {
        $token = $lifetime === null ? null : bin2hex(random_bytes(32));
        $expiry = $lifetime === null ? null : $now + $lifetime;
        $minimumExpiry = max($now + self::IDLE_LIFETIME, $expiry ?? 0);
        $statement = $this->database->prepare('UPDATE member_sessions
            SET remember_digest = ?, remember_expires_at = ?, last_seen_at = ?,
                expires_at = CASE WHEN trust_expires_at > ? THEN trust_expires_at ELSE ? END
            WHERE member_id = ? AND id = ?');
        $statement->bindValue(1, $token === null ? null : hash('sha256', $token));
        $statement->bindValue(2, $expiry);
        $statement->bindValue(3, $now, PDO::PARAM_INT);
        $statement->bindValue(4, $minimumExpiry, PDO::PARAM_INT);
        $statement->bindValue(5, $minimumExpiry, PDO::PARAM_INT);
        SqlDialect::bindBinary($this->database, $statement, 6, $member);
        $statement->bindValue(7, $id);
        $statement->execute();

        return $token === null ? [] : [self::REMEMBER => $token];
    }

    public function validate(string $member, string $id, string $credential, int $now): bool
    {
        if (!self::validId($id)) {
            return false;
        }
        $statement = $this->database->prepare('SELECT m.password, s.last_seen_at FROM member_sessions s
            JOIN member m ON m.uuid = s.member_id
            WHERE s.member_id = ? AND s.id = ? AND s.expires_at > ? AND m.username IS NOT NULL');
        SqlDialect::bindBinary($this->database, $statement, 1, $member);
        $statement->bindValue(2, $id);
        $statement->bindValue(3, $now, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if (!is_array($row) || !is_string($row['password'] ?? null)
            || !hash_equals(SessionLifecycle::credential($row['password']), $credential)) {
            return false;
        }
        if (is_numeric($row['last_seen_at'] ?? null) && (int) $row['last_seen_at'] <= $now - self::TOUCH_INTERVAL) {
            $this->touch($member, $id, $now);
        }

        return true;
    }

    public function touch(string $member, string $id, int $now): void
    {
        $until = $now + self::IDLE_LIFETIME;
        $statement = $this->database->prepare('UPDATE member_sessions SET last_seen_at = ?,
            expires_at = CASE WHEN expires_at < ? THEN ? ELSE expires_at END
            WHERE member_id = ? AND id = ? AND expires_at > ? AND last_seen_at <= ?');
        $statement->bindValue(1, $now, PDO::PARAM_INT);
        $statement->bindValue(2, $until, PDO::PARAM_INT);
        $statement->bindValue(3, $until, PDO::PARAM_INT);
        SqlDialect::bindBinary($this->database, $statement, 4, $member);
        $statement->bindValue(5, $id);
        $statement->bindValue(6, $now, PDO::PARAM_INT);
        $statement->bindValue(7, $now - self::TOUCH_INTERVAL, PDO::PARAM_INT);
        $statement->execute();
    }

    /** @return list<array{id: string, user_agent: string, created_at: int, last_seen_at: int}> */
    public function listActive(string $member, int $now): array
    {
        $statement = $this->database->prepare('SELECT id, user_agent, created_at, last_seen_at FROM member_sessions
            WHERE member_id = ? AND expires_at > ? ORDER BY last_seen_at DESC, id');
        SqlDialect::bindBinary($this->database, $statement, 1, $member);
        $statement->bindValue(2, $now, PDO::PARAM_INT);
        $statement->execute();
        $sessions = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row) || !is_string($row['id'] ?? null) || !is_string($row['user_agent'] ?? null)
                || !is_numeric($row['created_at'] ?? null) || !is_numeric($row['last_seen_at'] ?? null)) {
                throw new RuntimeException('Malformed registered session.');
            }
            $sessions[] = [
                'id' => $row['id'],
                'user_agent' => $row['user_agent'],
                'created_at' => (int) $row['created_at'],
                'last_seen_at' => (int) $row['last_seen_at'],
            ];
        }

        return $sessions;
    }

    public function revoke(string $member, string $id): bool
    {
        $statement = $this->database->prepare('DELETE FROM member_sessions WHERE member_id = ? AND id = ?');
        SqlDialect::bindBinary($this->database, $statement, 1, $member);
        $statement->bindValue(2, $id);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    public function revokeOthers(string $member, string $currentId): void
    {
        $statement = $this->database->prepare('DELETE FROM member_sessions WHERE member_id = ? AND id <> ?');
        SqlDialect::bindBinary($this->database, $statement, 1, $member);
        $statement->bindValue(2, $currentId);
        $statement->execute();
    }

    public function revokePersistentCookies(string $member): void
    {
        $statement = $this->database->prepare('UPDATE member_sessions SET remember_digest = NULL, remember_expires_at = NULL,
            trust_digest = NULL, trust_expires_at = NULL, expires_at = last_seen_at + ?
            WHERE member_id = ?');
        $statement->bindValue(1, self::IDLE_LIFETIME, PDO::PARAM_INT);
        SqlDialect::bindBinary($this->database, $statement, 2, $member);
        $statement->execute();
    }
}
