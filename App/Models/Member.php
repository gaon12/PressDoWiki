<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Http\Security\{EmailVerification, EmailVerificationPurpose, SessionLifecycle};

class Member extends \PressDo\App\Core\Model
{
    /**
     * check member info
     * @param string $id        username/email
     * @param string $pw      userpw
     * @return array{uuid: string, credential: string}|false
     */
    public static function checkMember(string $id, string $pw): array|false
    {
        $db = self::db();
        try {
            $d = $db->prepare('SELECT password, ' . SqlDialect::binaryHex('uuid') . ' AS uuid_hex FROM member WHERE username = ? OR email = ?');
            $d->execute([$id, $id]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 유저 조회 중 오류 발생');
        }
        
        $user = $d->fetch(PDO::FETCH_ASSOC);
        
        // not found
        if ($user === false || !is_string($user['password']) || !password_verify($pw, $user['password']))
            return false;
        
        return [
            'uuid' => self::bin2uuid(hex2bin($user['uuid_hex'])),
            'credential' => SessionLifecycle::credential($user['password']),
        ];
    }

    public static function sessionCredential(string $uuid): ?string
    {
        $d = self::db()->prepare('SELECT password FROM member WHERE uuid = ? AND username IS NOT NULL');
        $d->bindValue(1, self::uuid2bin($uuid), self::binaryParameterType());
        $d->execute();
        $hash = $d->fetchColumn();

        return is_string($hash) ? SessionLifecycle::credential($hash) : null;
    }

    /**
     * @param array<string, int> $cookieLifetimes
     * @return array{username: string, email: string, skin: ?string, cookies: array<string, string>}|null
     */
    public static function login(string $uuid, string $credential, string $ip, string $ua, array $cookieLifetimes = []): ?array
    {
        $db = self::db();
        $member = self::uuid2bin($uuid);
        $db->beginTransaction();

        try {
            // Share the account lock with password reset before issuing persistent cookies.
            $lock = SqlDialect::isSqlite() ? '' : ' FOR UPDATE';
            $d = $db->prepare('SELECT username, skin, email, password FROM member WHERE uuid = ? AND username IS NOT NULL' . $lock);
            $d->bindValue(1, $member, self::binaryParameterType());
            $d->execute();
            $user = $d->fetch(PDO::FETCH_ASSOC);
            if (!is_string($user['password'] ?? null) || !hash_equals(SessionLifecycle::credential($user['password']), $credential)) {
                $db->rollBack();
                return null;
            }

            $d = $db->prepare('INSERT INTO login_history (uuid, ip) VALUES (?, ?)');
            $d->bindValue(1, $member, self::binaryParameterType());
            $d->bindValue(2, $ip);
            $d->execute();

            $d = $db->prepare('UPDATE member SET last_login_ua = ? WHERE uuid = ?');
            $d->bindValue(1, $ua);
            $d->bindValue(2, $member, self::binaryParameterType());
            $d->execute();

            $cookies = [];
            foreach ($cookieLifetimes as $name => $lifetime) {
                $cookies[$name] = self::saveCookies($uuid, $name, $lifetime);
            }

            $db->commit();

            return ['username' => $user['username'], 'email' => $user['email'], 'skin' => $user['skin'], 'cookies' => $cookies];
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
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
            $update->execute([$secret, $member, $previous]);
            if ($update->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            $revoke = $db->prepare(SqlDialect::normalizeIdentifiers('DELETE FROM cookies WHERE `user` = ?'));
            $revoke->execute([$member]);

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
        $d->execute([$uuid]);
        $data = $d->fetch(PDO::FETCH_ASSOC);
        if($data === false)
            return false;
        else
            return inet_ntop($data['ip']);
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
        $d->execute([$uuid]);
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

    /**
     * Save cookie value to database.
     * @param string $uuid
     * @param string $name
     * @param int $length
     * @return string value of saved cookie (in hex)
     */
    public static function saveCookies(string $uuid, string $name, int $length): string
    {
        $db = self::db();
        $exec = self::uuid2bin($uuid);
        $token = random_bytes(16);

        $d = $db->prepare(SqlDialect::normalizeIdentifiers("INSERT INTO cookies (`user`, `name`, `value`, expiry) VALUES (?,?,?,?)"));
        $d->bindValue(1, $exec, self::binaryParameterType());
        $d->bindValue(2, $name);
        $d->bindValue(3, $token, self::binaryParameterType());
        $d->bindValue(4, time() + $length, PDO::PARAM_INT);
        $d->execute();
        return bin2hex($token);
    }

    /** @return array{uuid: string, credential: string}|null */
    public static function rememberCookieIdentity(string $value): ?array
    {
        $token = self::decodeCookie($value);
        if ($token === null) {
            return null;
        }

        $uuid = SqlDialect::binaryHex('member.uuid');
        $d = self::db()->prepare(SqlDialect::normalizeIdentifiers(
            "SELECT $uuid AS uuid_hex, member.password FROM cookies JOIN member ON member.uuid = cookies.`user`
            WHERE cookies.name = 'szczecin' AND cookies.value = ? AND cookies.expiry > ? AND member.username IS NOT NULL"
        ));
        $d->bindValue(1, $token, self::binaryParameterType());
        $d->bindValue(2, time(), PDO::PARAM_INT);
        $d->execute();
        $user = $d->fetch(PDO::FETCH_ASSOC);
        if (!is_string($user['password'] ?? null)) {
            return null;
        }

        return [
            'uuid' => self::bin2uuid(hex2bin($user['uuid_hex'])),
            'credential' => SessionLifecycle::credential($user['password']),
        ];
    }

    public static function checkCookie(string $name, string $value, ?string $expectedUser = null): ?string
    {
        $token = self::decodeCookie($value);
        if ($token === null) {
            return null;
        }

        $db = self::db();
        $userHex = SqlDialect::binaryHex('`user`');
        $d = $db->prepare(SqlDialect::normalizeIdentifiers(
            "SELECT $userHex FROM cookies WHERE `name` = ? AND `value` = ? AND expiry > ?"
        ));
        $d->bindValue(1, $name);
        $d->bindValue(2, $token, self::binaryParameterType());
        $d->bindValue(3, time(), PDO::PARAM_INT);
        $d->execute();
        $user = $d->fetchColumn();
        if (!is_string($user)) {
            return null;
        }

        $uuid = self::bin2uuid(hex2bin($user));

        return $expectedUser === null || $uuid === $expectedUser ? $uuid : null;
    }

    public static function deleteCookie(string $uuid, string $name, string $value): void
    {
        $token = self::decodeCookie($value);
        if ($token === null) {
            return;
        }

        $db = self::db();
        $d = $db->prepare(SqlDialect::normalizeIdentifiers(
            'DELETE FROM cookies WHERE `user` = ? AND `name` = ? AND `value` = ?'
        ));
        $d->bindValue(1, self::uuid2bin($uuid), self::binaryParameterType());
        $d->bindValue(2, $name);
        $d->bindValue(3, $token, self::binaryParameterType());
        $d->execute();
    }

    private static function binaryParameterType(): int
    {
        // Existing SQLite schemas may hold identifiers written as TEXT parameters.
        return SqlDialect::isPostgresql() ? PDO::PARAM_LOB : PDO::PARAM_STR;
    }

    private static function decodeCookie(string $value): ?string
    {
        if (strlen($value) !== 32 || !ctype_xdigit($value)) {
            return null;
        }

        return hex2bin($value) ?: null;
    }

}
