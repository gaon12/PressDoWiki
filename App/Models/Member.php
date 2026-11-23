<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;
use PressDo\App\Http\Security\{EmailVerification, EmailVerificationPurpose};

class Member extends \PressDo\App\Core\Model
{
    /**
     * check member info
     * @param string $id        username/email
     * @param string $pw      userpw
     * @return array|bool        false or array(username, uuid, password)
     */
    public static function checkMember(string $id, string $pw): array|bool
    {
        $db = self::db();
        try {
            $d = $db->prepare("SELECT `username`, `password`, `uuid` FROM `member` WHERE `username`=:id OR email=:id");
            $d->execute(['id' => $id]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 유저 조회 중 오류 발생');
        }
        
        $user = $d->fetch(PDO::FETCH_ASSOC);
        
        // not found
        if ($user === false || !password_verify($pw, $user['password']))
            return false;
        
        $user['uuid'] = self::bin2uuid($user['uuid']);
        return $user;
    }

    /**
     * login user
     * @param string $uuid        uuid
     * @param string $ip       user IP
     * @param string $ua       user-agent
     * @return array|bool        false or userdata
     */
    public static function login(string $uuid, string $ip, string $ua): array
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        try {
            $d = $db->prepare("SELECT `username`, `skin`, `email` FROM `member` WHERE `uuid`=?");
            $d->execute([$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 유저 조회 중 오류 발생');
        }
        
        $user = $d->fetch(PDO::FETCH_ASSOC);
        
        try {
            $d = $db->prepare("INSERT INTO `login_history`(uuid,ip) VALUES(?,?)");
            $d->execute([$uuid, $ip]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 로그인 기록 중 오류 발생');
        }
        
        try {
            $d = $db->prepare("UPDATE `member` SET `last_login_ua`=? WHERE `uuid`=?");
            $d->execute([$ua, $uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 로그인 처리 중 오류 발생');
        }
        
        return $user;
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
            $data['uuid'] = self::bin2uuid($data['uuid']);
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
                $insert->execute([$memberUuid, $email, $username, $hash]);
            } else {
                if ($member['username'] !== null) {
                    return false;
                }
                $memberUuid = $member['uuid'];
                $update = $db->prepare('UPDATE member SET username = ?, password = ? WHERE uuid = ? AND username IS NULL');
                $update->execute([$username, $hash, $memberUuid]);
                if ($update->rowCount() !== 1) {
                    return false;
                }
            }

            $documentUuid = self::uuid2bin(self::generateUuid());
            $revisionUuid = self::uuid2bin(self::generateUuid());
            $insert = $db->prepare("INSERT INTO document (uuid, namespace, title, backlink_updated) VALUES (?, '사용자', ?, 1)");
            $insert->execute([$documentUuid, $username]);
            $insert = $db->prepare("INSERT INTO history (uuid, document, datetime, action, contributor_m) VALUES (?, ?, ?, 'create', ?)");
            $insert->execute([$revisionUuid, $documentUuid, time(), $memberUuid]);

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
            $d = $db->prepare("SELECT `email`, `perm`, `totp_secret` FROM `member` WHERE uuid=?");
            $d->execute([self::uuid2bin($uuid)]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 사용자 정보 조회 중 오류 발생');
        }

        return $d->fetch(PDO::FETCH_ASSOC);
    }

    public static function setTotp(string $uuid, string $secret)
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        
        try {
            $e = $db->prepare("UPDATE `member` SET `totp_secret`=? WHERE `uuid`=?");
            $e->execute([$secret,$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': TOTP 등록 중 오류 발생');
        }
    }

    public static function removeTotp(string $uuid)
    {
        $db = self::db();
        $uuid = self::uuid2bin($uuid);
        
        try {
            $e = $db->prepare("UPDATE `member` SET `totp_secret`=NULL WHERE `uuid`=?");
            $e->execute([$uuid]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': TOTP 삭제 중 오류 발생');
        }
    }

    public static function getUserWebauthn(string $uuid): array
    {
        $db = self::db();
        try {
            $d = $db->prepare("SELECT `name`, `registered`, `lastuse`, client_data FROM `webauthn` WHERE uuid=? ORDER BY registered ASC");
            $d->execute([self::uuid2bin($uuid)]);
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

    public static function logWebauthnUsage(string $name)
    {
        $db = self::db();
        try {
            $d = $db->prepare("UPDATE `webauthn` SET lastuse=? WHERE `name`=?");
            $d->execute([time(), $name]);
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
            $update->execute([implode(',', $perms), $target, $expected]);
            if ($update->rowCount() !== 1) {
                $db->rollBack();
                return false;
            }

            $audit = $db->prepare(SqlDialect::normalizeIdentifiers(
                "INSERT INTO `BlockHistory` (executor_m, target_member, action, granted) VALUES (?, ?, 'grant', ?)"
            ));
            $audit->execute([$executor, $target, $record]);

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
        $d->execute([$exec, $name, $token, time() + $length]);
        return bin2hex($token);
    }

    public static function checkCookie(string $name, string $value, ?string $expectedUser = null): ?string
    {
        $token = self::decodeCookie($value);
        if ($token === null) {
            return null;
        }

        $db = self::db();
        $d = $db->prepare(SqlDialect::normalizeIdentifiers(
            'SELECT `user` FROM cookies WHERE `name` = ? AND `value` = ? AND expiry > ?'
        ));
        $d->execute([$name, $token, time()]);
        $user = $d->fetchColumn();
        if (!is_string($user)) {
            return null;
        }

        $uuid = self::bin2uuid($user);

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
        $d->execute([self::uuid2bin($uuid), $name, $token]);
    }

    private static function decodeCookie(string $value): ?string
    {
        if (strlen($value) !== 32 || !ctype_xdigit($value)) {
            return null;
        }

        return hex2bin($value) ?: null;
    }

}
