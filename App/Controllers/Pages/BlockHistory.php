<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Models\{BlockHistory as BH, Member, ACL};
use PressDo\App\Core\Controller;
use PressDo\App\Helpers\{Namespaces,Languages,Config};

class BlockHistory extends Controller
{
    public function makeData(): array
    {
        $from = (int) $this->request->queryString('from') ?: null;
        $until = (int) $this->request->queryString('until');
        $type = $this->request->queryString('target') === 'author' ? 'author' : 'text';
        $keyword = $this->request->queryString('query');

        $data = array_reverse(BH::get($type, $keyword, $from, $until));
        $dataset = [];
        
        foreach ($data as $d){
            $ip = $member = $Cuuid = $target_ip = $target_member = $uuid2 = $aclgroup = null;

            if ($d['until'] === null)
                $dur = null;
            else
                $dur = $d['until'] == '0' ? 'forever' : self::formatTime($d['until'] - $d['datetime']);
            
            if ($d['executor_i'] !== null) {
                $Cuuid = BH::bin2uuid($d['executor_i']);
                $ip = Member::ipLookup($Cuuid);
                $member = null;
            } elseif($d['executor_m'] !== null) {
                $Cuuid = BH::bin2uuid($d['executor_m']);
                $member = Member::lookup($Cuuid);
                $ip = null;
            }

            if ($d['target_ip'] !== null) {
                $target_ip = inet_ntop($d['target_ip']).'/'.$d['mask'];
            }

            if ($d['target_member'] !== null) {
                $uuid2 = BH::bin2uuid($d['target_member']);
                $target_member = Member::lookup($uuid2);
            }

            if (isset($d['target_ip_uuid'])) {
                $uuid2 = BH::bin2uuid($d['target_ip_uuid']);
                $target_ip = Member::ipLookup($uuid2);
            }
            
            array_push($dataset, [
                'author' => $member,
                'author_ip' => $ip,
                'author_uuid' => $Cuuid,
                'datetime' => $d['datetime'],
                'action' => $d['action'],
                'content' => [
                    'id' => $d['id'],
                    'ip' => $target_ip,
                    'duration' => $dur,
                    'memo' => $d['comment'],
                    'member' => $target_member,
                    'target_uuid' => $uuid2,
                    'aclgroup' => $d['target_aclgroup'],
                    'target_id' => $d['target_id'],
                    'granted' => $d['granted']
                ]
            ]);
        }
        
        $page = [
            'view_name' => 'BlockHistory',
            'title' => Languages::get('page')['BlockHistory'],
            'data' => [
                'prev_page' => null,
                'next_page' => null,
                'history' => $dataset
            ]
        ];

        // 첫 항목이 (조건 내에서) 최신 항목이 아닌 경우 띄움
        ['max' => $max, 'min' => $min] = BH::get_block_history_count($type, $keyword);
        if ($dataset !== [] && $dataset[0]['content']['id'] !== $max)
            $page['data']['prev_page'] = $page['data']['history'][0]['content']['id'] + 1;
        if ($dataset !== [] && end($dataset)['content']['id'] !== $min)
            $page['data']['next_page'] = end($page['data']['history'])['content']['id'] - 1;

        return $page;
    }
}