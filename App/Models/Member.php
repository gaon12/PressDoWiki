<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Http\Security\{EmailVerification, EmailVerificationPurpose, SessionLifecycle, SessionRegistry};

class Member extends \PressDo\App\Core\Model
{
    /**
     * check member info
     * @param string $id        username/email
     * @param string $pw      userpw
     * @param (callable(?string): bool)|null $beforeVerify
     * @return array{uuid: string, credential: string}|false
     */
    public static function checkMember(string $id, string $pw, ?callable $beforeVerify = null): array|false
    {
        $db = self::db();
        try {
            $d = $db->prepare('SELECT password, ' . SqlDialect::binaryHex('uuid') . ' AS uuid_hex FROM member WHERE username = ? OR email = ?');
            $d->execute([$id, $id]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 유저 조회 중 오류 발생');
        }
        
        $user = $d->fetch(PDO::FETCH_ASSOC);
        $d->closeCursor();
        $hash = $user['password'] ?? null;
        $uuid = is_string($hash) ? self::bin2uuid(hex2bin($user['uuid_hex'])) : null;
        if ($beforeVerify !== null && !$beforeVerify($uuid)) {
            return false;
        }
        if ($uuid === null || !password_verify($pw, $hash)) {
            return false;
        }

        return [
            'uuid' => $uuid,
            'credential' => SessionLifecycle::credential($hash),
        ];
    }

    public static function sessionCredential(string $uuid): ?string
    {
        $db = self::db();
        $d = $db->prepare('SELECT password FROM member WHERE uuid = ? AND username IS NOT NULL');
        SqlDialect::bindBinary($db, $d, 1, self::uuid2bin($uuid));
        $d->execute();
        $hash = $d->fetchColumn();

        return is_string($hash) ? SessionLifecycle::credential($hash) : null;
    }

    public static function validateSession(string $uuid, string $id, string $credential): bool
    {
        return (new SessionRegistry(self::db()))->validate(self::uuid2bin($uuid), $id, $credential, time());
    }

    /**
     * @param array<string, int> $cookieLifetimes
     * @return array{username: string, email: string, skin: ?string, device_session: string, cookies: array<string, string>}|null
     */
    public static function login(
        string $uuid,
        string $credential,
        string $ip,
        string $ua,
        array $cookieLifetimes = [],
        ?string $rememberToken = null,
        ?string $trustedToken = null,
    ): ?array {
        $db = self::db();
        $member = self::uuid2bin($uuid);
        $db->beginTransaction();

        try {
            $user = self::lockLoginAccount($db, $member);
            if (!is_string($user['password'] ?? null) || !hash_equals(SessionLifecycle::credential($user['password']), $credential)) {
                $db->rollBack();
                return null;
            }

            $now = time();
            $registry = new SessionRegistry($db);
            $token = $rememberToken ?? $trustedToken;
            if ($token === null) {
                $device = $registry->create($member, $ua, $now, $cookieLifetimes);
            } else {
                $kind = $rememberToken !== null ? SessionRegistry::REMEMBER : SessionRegistry::TRUST;
                $device = $registry->findByCookie($kind, $token, $now, lock: true);
                if ($device === null || !hash_equals($member, $device['member'])) {
                    $db->rollBack();
                    return null;
                }
                if ($kind === SessionRegistry::TRUST) {
                    $device['cookies'] = $registry->renewRemember($member, $device['id'], $cookieLifetimes[SessionRegistry::REMEMBER] ?? null, $now);
                } else {
                    $registry->touch($member, $device['id'], $now);
                    $device['cookies'] = [];
                }
            }

            $d = $db->prepare('INSERT INTO login_history (uuid, ip) VALUES (?, ?)');
            SqlDialect::bindBinary($db, $d, 1, $member);
            $d->bindValue(2, $ip);
            $d->execute();

            $d = $db->prepare('UPDATE member SET last_login_ua = ? WHERE uuid = ?');
            $d->bindValue(1, $ua);
            SqlDialect::bindBinary($db, $d, 2, $member);
            $d->execute();

            $db->commit();

            return [
                'username' => $user['username'],
                'email' => $user['email'],
                'skin' => $user['skin'],
                'device_session' => $device['id'],
                'cookies' => $device['cookies'],
            ];
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function lockLoginAccount(PDO $db, string $member): array|false
    {
        $sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if ($sqlite) {
            // Acquire SQLite's write lock before reading credentials or browser tokens.
            $d = $db->prepare('UPDATE member SET last_login_ua = last_login_ua WHERE uuid = ?');
            SqlDialect::bindBinary($db, $d, 1, $member);
            $d->execute();
        }
        $d = $db->prepare('SELECT username, skin, email, password FROM member WHERE uuid = ? AND username IS NOT NULL'
            . ($sqlite ? '' : ' FOR UPDATE'));
        SqlDialect::bindBinary($db, $d, 1, $member);
        $d->execute();

        return $d->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if member exists.
     * @param mixed $username
     * @param mixed $email
     * @throws \ErrorException
     * @return array|bool           Member's uuid, username and email; false if not exists
     */
    public static function exist($username='', $email=''): array|bool
    {
        $db = self::db();
        try{
            if (empty($email)) {
                $d = $db->prepare("SELECT uuid, username, email, last_login_ua, perm FROM member WHERE username=?");
                $d->execute([$username]);
            } else {
                $d = $db->prepare("SELECT uuid, username, email, last_login_ua, perm FROM member WHERE email=? AND username IS NOT NULL");
                $d->execute([$email]);
            }
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 사용자 조회 중 오류 발생');
        }
        $data = $d->fetch(PDO::FETCH_ASSOC);
        if($data === false)
            return false;
        else {
            $data['uuid'] = self::bin2uuid(self::readBinary($data['uuid']));
            return $data;
        }
    }

    public static function issueEmailCode(string $email, string $ip, EmailVerificationPurpose $purpose): ?string
    {
        return (new EmailVerification(self::db()))->issue($email, $ip, $purpose);
    }

    public static function verifyEmailCode(string $token, EmailVerificationPurpose $purpose, string $ip = ''): ?string
    {
        return (new EmailVerification(self::db()))->verify($token, $purpose, $ip);
    }

    public static function completeSignup(string $token, string $ip, string $username, string $password): bool
    {
        $db = self::db();
        $verification = new EmailVerification($db);
        return $verification->consume($token, EmailVerificationPurpose::Signup, static function (string $email) use ($db, $username, $password): bool {
            $lookup = $db->prepare('SELECT uuid, username FROM member WHERE email = ?');
            $lookup->execute([$email]);
            $member = $lookup->fetch(PDO::FETCH_ASSOC);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            if ($member === false) {
                $memberUuid = self::uuid2bin(self::generateUuid());
                $insert = $db->prepare('INSERT INTO member (uuid, email, username, password) VALUES (?, ?, ?, ?)');
                SqlDialect::bindBinary($db, $insert, 1, $memberUuid);
                $insert->bindValue(2, $email);
                $insert->bindValue(3, $username);
                $insert->bindValue(4, $hash);
                $insert->execute();
            } else {
                if ($member['username'] !== null) {
                    return false;
                }
                $memberUuid = self::readBinary($member['uuid']);
                $update = $db->prepare('UPDATE member SET username = ?, password = ? WHERE uuid = ? AND username IS NULL');
                $update->bindValue(1, $username);
                $update->bindValue(2, $hash);
                SqlDialect::bindBinary($db, $update, 3, $memberUuid);
                $update->execute();
                if ($update->rowCount() !== 1) {
                    return false;
                }
            }

            $documentUuid = self::uuid2bin(self::generateUuid());
            $revisionUuid = self::uuid2bin(self::generateUuid());
            $insert = $db->prepare("INSERT INTO document (uuid, namespace, title, backlink_updated) VALUES (?, '사용자', ?, 1)");
            SqlDialect::bindBinary($db, $insert, 1, $documentUuid);
            $insert->bindValue(2, $username);
            $insert->execute();
            $insert = $db->prepare("INSERT INTO history (uuid, document, datetime, action, contributor_m) VALUES (?, ?, ?, 'create', ?)");
            SqlDialect::bindBinary($db, $insert, 1, $revisionUuid);
            SqlDialect::bindBinary($db, $insert, 2, $documentUuid);
            $insert->bindValue(3, time(), PDO::PARAM_INT);
            SqlDialect::bindBinary($db, $insert, 4, $memberUuid);
            $insert->execute();

            return true;
        }, $ip);
    }

    public static function completePasswordReset(string $token, string $password): bool
    {
        $db = self::db();
        $verification = new EmailVerification($db);
        return $verification->consume($token, EmailVerificationPurpose::PasswordReset, static function (string $email) use ($db, $password): bool {
            $update = $db->prepare('UPDATE member SET password = ? WHERE email = ? AND username IS NOT NULL');
            $update->execute([password_hash($password, PASSWORD_BCRYPT), $email]);
            if ($update->rowCount() !== 1) {
                return false;
            }

            $revoke = $db->prepare(SqlDialect::normalizeIdentifiers(
                'DELETE FROM cookies WHERE `user` = (SELECT uuid FROM member WHERE email = ?)'
            ));
            $revoke->execute([$email]);
            $revokeSessions = $db->prepare('DELETE FROM member_sessions WHERE member_id = (SELECT uuid FROM member WHERE email = ?)');
            $revokeSessions->execute([$email]);

            return true;
        });
    }

    public static function getUserInfo(string $uuid)
    {
        $db = self::db();
        try {
            $d = $db->prepare('SELECT email, perm, totp_secret FROM member WHERE uuid = ?');
            SqlDialect::bindBinary($db, $d, 1, self::uuid2bin($uuid));
            $d->execute();
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 사용자 정보 조회 중 오류 발생');
        }

        return $d->fetch(PDO::FETCH_ASSOC);
    }

    public static function setTotp(string $uuid, string $secret): bool
    {
        return self::changeTotp($uuid, '', $secret);
    }

    public static function removeTotp(string $uuid, string $secret): bool
    {
        return self::changeTotp($uuid, $secret, null);
    }

    private static function changeTotp(string $uuid, string $previous, ?string $secret): bool
    {
        $db = self::db();
        $member = self::uuid2bin($uuid);
        $db->beginTransaction();

        try {
            $update = $db->prepare("UPDATE member SET totp_secret = ? WHERE uuid = ? AND COALESCE(totp_secret, '') = ?");
            $update->bindValue(1, $secret);
            SqlDialect::bindBinary($db, $update, 2, $member);
            $update->bindValue(3, $previous);
            $update->execute();
            if ($update->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            $revoke = $db->prepare(SqlDialect::normalizeIdentifiers('DELETE FROM cookies WHERE `user` = ?'));
            SqlDialect::bindBinary($db, $revoke, 1, $member);
            $revoke->execute();
            (new SessionRegistry($db))->revokePersistentCookies($member);

            return $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public static function getUserWebauthn(string $uuid): array
    {
        $db = self::db();
        try {
            $d = $db->prepare('SELECT name, registered, lastuse, client_data FROM webauthn WHERE uuid = ? ORDER BY registered ASC');
            SqlDialect::bindBinary($db, $d, 1, self::uuid2bin($uuid));
            $d->execute();
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': Webauthn 목록 조회 중 오류 발생');
        }

        return $d->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveUserWebauthn(string $uuid, string $name, string $data): void
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        
        try {
            $e = $db->prepare("INSERT INTO `webauthn` (uuid, `name`, client_data) VALUES (?, ?, ?)");
            $e->execute([$uuid,$name,$data]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': Webauthn 추가 중 오류 발생');
        }
    }

    public static function deleteUserWebauthn(string $uuid, string $name): void
    {
        $db = self::db();
        try {
            $d = $db->prepare("DELETE FROM `webauthn` WHERE uuid=? AND `name`=?");
            $d->execute([self::uuid2bin($uuid), $name]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': Webauthn 삭제 중 오류 발생');
        }
    }

    public static function logWebauthnUsage(string $uuid, string $name): void
    {
        $db = self::db();
        try {
            $d = $db->prepare("UPDATE `webauthn` SET lastuse=? WHERE uuid=? AND `name`=?");
            $d->execute([time(), self::uuid2bin($uuid), $name]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': Webauthn 사용 기록 중 오류 발생');
        }
    }

    public static function grantPermissions(string $executoruuid, string $targetuuid, string $expected, array $perms, string $record): bool
    {
        $db = self::db();
        $executor = self::uuid2bin($executoruuid);
        $target = self::uuid2bin($targetuuid);
        $db->beginTransaction();

        try {
            $update = $db->prepare("UPDATE member SET perm = ? WHERE uuid = ? AND COALESCE(perm, '') = ?");
            $update->bindValue(1, implode(',', $perms));
            SqlDialect::bindBinary($db, $update, 2, $target);
            $update->bindValue(3, $expected);
            $update->execute();
            if ($update->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            $audit = $db->prepare(SqlDialect::normalizeIdentifiers(
                "INSERT INTO `BlockHistory` (executor_m, target_member, action, granted) VALUES (?, ?, 'grant', ?)"
            ));
            SqlDialect::bindBinary($db, $audit, 1, $executor);
            SqlDialect::bindBinary($db, $audit, 2, $target);
            $audit->bindValue(3, $record);
            $audit->execute();

            return $db->commit();
        } catch (\Throwable $err) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $err;
        }
    }

    /**
     * find ip by uuid
     * @param string $uuid
     * @return mixed
     */
    public static function ipLookup($uuid): bool|string
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        $d = $db->prepare("SELECT ip FROM ip WHERE uuid=?");
        SqlDialect::bindBinary($db, $d, 1, $uuid);
        $d->execute();
        $data = $d->fetch(PDO::FETCH_ASSOC);
        if($data === false)
            return false;
        else
            return inet_ntop(self::readBinary($data['ip']));
    }

    /**
     * find username by uuid
     * @param string $uuid
     * @return string|false username (false if not exist)
     */
    public static function lookup($uuid): string|bool
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        $d = $db->prepare("SELECT username FROM member WHERE uuid=?");
        SqlDialect::bindBinary($db, $d, 1, $uuid);
        $d->execute();
        $data = $d->fetch(PDO::FETCH_ASSOC);
        if($data === false)
            return false;
        else
            return $data['username'];
    }

    public static function loginHistory(string $id, string $execuuid, $from=null, $until=null)
    {
        $db = self::db();
        $exec = self::uuid2bin($execuuid);
        $target = self::uuid2bin($id);
        $sqlstr = '';

        $params = [$target];
        if($from !== null) {
            $sqlstr = "AND `datetime`<=?";
            $params[] = (int) $from;
        } elseif($until !== null) {
            $sqlstr = "AND `datetime`>=?";
            $params[] = (int) $until;
        }

        

        $d = $db->prepare("SELECT ip, `datetime` FROM `login_history` WHERE `uuid`=? $sqlstr ORDER BY `datetime` DESC LIMIT 50");
        $d->execute($params);
        $e = $db->prepare("INSERT INTO `BlockHistory` (id,executor_m,target_member,action) VALUES(NULL,?,?,'login_history')");
        $e->execute([$exec, $target]);
        return $d->fetchAll();
    }

    public static function getLogintimeEnd(string $uuid): array
    {
        $db = self::db();
        $exec = self::uuid2bin($uuid);

        $d = $db->prepare("SELECT MAX(`datetime`) AS max, MIN(`datetime`) AS min FROM `login_history` WHERE `uuid`=? ORDER BY `datetime`");
        $d->execute([$exec]);
        return $d->fetch();
    }

    /** @return array{uuid: string, credential: string}|null */
    public static function rememberCookieIdentity(string $value): ?array
    {
        $uuid = self::checkCookie(SessionRegistry::REMEMBER, $value);
        if ($uuid === null) {
            return null;
        }

        $credential = self::sessionCredential($uuid);
        return $credential === null ? null : ['uuid' => $uuid, 'credential' => $credential];
    }

    public static function checkCookie(string $name, string $value, ?string $expectedUser = null): ?string
    {
        $device = (new SessionRegistry(self::db()))->findByCookie($name, $value, time());
        if ($device === null) {
            return null;
        }

        $uuid = self::bin2uuid($device['member']);
        return $expectedUser === null || $uuid === $expectedUser ? $uuid : null;
    }

    public static function revokeLoginSession(string $uuid, string $id): void
    {
        (new SessionRegistry(self::db()))->revoke(self::uuid2bin($uuid), $id);
    }

}
