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
            if (!empty($query) && $type == 'author') {
                $a = $db->prepare("SELECT b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted
                FROM `BlockHistory` b JOIN member ON b.executor_m = member.uuid WHERE member.username=? AND $sqlstr ORDER BY b.id ASC LIMIT 100");
                $a->execute(array_merge([trim($query)], $rangeParams));
            } elseif (!empty($query) && $type == 'text') {
                if (SqlDialect::isSqlite()) {
                    $sql = "SELECT b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted
                    FROM `BlockHistory` b LEFT JOIN member ON member.uuid = b.target_member LEFT JOIN aclgroups ON aclgroups.name = b.target_aclgroup
                    WHERE (member.username LIKE ? OR aclgroups.name LIKE ? OR b.comment LIKE ? OR b.id LIKE ?) AND $sqlstr ORDER BY id ASC LIMIT 100";
                    $a = $db->prepare($sql);
                    $q = '%'.$query.'%';
                    $a->execute(array_merge([$q, $q, $q, $q], $rangeParams));
                } else {
                    $sql = "SELECT b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted
                FROM `BlockHistory` b JOIN member ON member.uuid = b.target_member AND member.username LIKE ?
                WHERE $sqlstr
                UNION SELECT b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted
                FROM `BlockHistory` b JOIN aclgroups ON aclgroups.name = b.target_aclgroup AND aclgroups.name LIKE ?
                WHERE $sqlstr
                UNION SELECT b.id,b.executor_m,b.executor_i,b.target_ip,b.mask,b.target_member,b.target_aclgroup,b.comment,b.`datetime`,b.until,b.`action`,b.granted
                FROM `BlockHistory` b WHERE ((INET_NTOA(CONV(HEX(`target_ip`), 16, 10)) LIKE ? OR INET6_NTOA(`target_ip`) LIKE ?) AND `mask` LIKE ? OR `comment` LIKE ? OR `id` LIKE ? ) AND $sqlstr ORDER BY id ASC LIMIT 100";
                    $a = $db->prepare($sql);
                
                    $ipv4string = explode('/', $query);
                    if (count($ipv4string) > 1)
                        $m = '%'.$ipv4string[1].'%';
                    else
                        $m = '%'.$ipv4string[0].'%';

                    $ipv6string = '%'.str_replace(':', '', $ipv4string[0]).'%';

                    $q = '%'.$query.'%';
                    $a->execute(array_merge([$q], $rangeParams, [$q], $rangeParams, ['%'.$ipv4string[0].'%',$ipv6string,$m,$q,$q], $rangeParams));
                }
            } else {
                $a = $db->prepare("SELECT id,executor_m,executor_i,target_ip,mask,target_member,target_aclgroup,comment,`datetime`,until,`action`,granted FROM `BlockHistory` WHERE $sqlstr ORDER BY id ASC LIMIT 100");
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
