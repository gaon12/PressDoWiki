<?php
namespace PressDo\App\Models;

use \PDO as PDO;
use \PDOException as PDOException;
use \ErrorException as ErrorException;
use PressDo\App\Helpers\SqlDialect;

class BlockHistory extends \PressDo\App\Core\Model
{
    public static function get(string $type='text', string $query='', $from=null, int $until=1): array
    {
        $db = self::db();
        $rangeParams = [];
        if ($from !== null) {
            $sqlstr = "id<=? AND id>=?";
            $rangeParams = [(int) $from, $until];
        } else {
            $sqlstr = "id>=?";
            $rangeParams = [$until];
        }

        try{
            $columns = 'b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted';
            if (!empty($query) && $type == 'author') {
                $a = $db->prepare(SqlDialect::normalizeIdentifiers("SELECT $columns
                FROM `BlockHistory` b JOIN member ON b.executor_m = member.uuid WHERE member.username=? AND $sqlstr ORDER BY b.id ASC LIMIT 100"));
                $a->execute(array_merge([trim($query)], $rangeParams));
            } elseif (!empty($query) && $type == 'text') {
                $sql = "SELECT $columns
                FROM `BlockHistory` b LEFT JOIN member ON member.uuid = b.target_member LEFT JOIN aclgroups ON aclgroups.name = b.target_aclgroup
                WHERE (member.username LIKE ? OR aclgroups.name LIKE ? OR ".SqlDialect::blockHistoryTextCondition().") AND $sqlstr ORDER BY b.id ASC LIMIT 100";
                $a = $db->prepare(SqlDialect::normalizeIdentifiers($sql));

                $ip = explode('/', $query)[0];
                $mask = str_contains($query, '/') ? substr(strrchr($query, '/'), 1) : $query;
                $packedIp = @inet_pton($ip);
                $ipHex = $packedIp === false ? str_replace(':', '', $ip) : bin2hex($packedIp);
                $q = '%'.$query.'%';
                $textParams = SqlDialect::isMysql()
                    ? ['%'.$ip.'%', '%'.str_replace(':', '', $ip).'%', '%'.$mask.'%', $q, $q]
                    : ['%'.strtolower($ipHex).'%', '%'.$mask.'%', $q, $q];
                $a->execute(array_merge([$q, $q], $textParams, $rangeParams));
            } else {
                $a = $db->prepare(SqlDialect::normalizeIdentifiers("SELECT id,executor_m,executor_i,target_ip,mask,target_member,target_aclgroup,comment,`datetime`,until,`action`,granted FROM `BlockHistory` WHERE $sqlstr ORDER BY id ASC LIMIT 100"));
                $a->execute($rangeParams);
            }

        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 차단 기록 로드 중 오류 발생');
        }
        return $a->fetchAll();
    }

    /**
     * Get total count of block history in certain condition.
     * Used in order to get latest history ID.
     * @return array [$max, $min]
     */
    public static function get_block_history_count(string $type, string $query): array
    {
        $db = self::db();
        try {
            if (!empty($query) && $type == 'author') {
                $a = $db->prepare("SELECT max(b.id) as max, min(b.id) as min FROM `BlockHistory` b JOIN member ON b.executor_m = member.uuid WHERE member.username=?");
                $a->execute([trim($query)]);
            } elseif (!empty($query) && $type == 'text') {
                $a = $db->prepare("SELECT max(id) as max, min(id) as min FROM `BlockHistory` WHERE (`target_ip` LIKE ? OR `comment` LIKE ? OR `target_member` LIKE ? OR `target_aclgroup` LIKE ? OR `id` LIKE ? )");
                $q = '%'.$query.'%';
                $a->execute([$q,$q,$q,$q,$q]);
            } else {
                $a = $db->query("SELECT max(id) as max, min(id) as min FROM `BlockHistory`");
            }
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage().': 차단 기록 인덱스 조회 중 오류 발생');
        }
        return $a->fetch();
    }
}
