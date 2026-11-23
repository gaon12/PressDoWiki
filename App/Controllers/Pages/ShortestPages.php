<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Models\Document;
use PressDo\App\Core\Controller;
use PressDo\App\Helpers\Languages;

class ShortestPages extends Controller
{
    public function makeData(): array
    {
        $requestedFrom = (int) $this->request->queryString('from');
        $requestedUntil = (int) $this->request->queryString('until');
        $from = 1;
        $count = 100;
        
        if ($requestedFrom > 0)
            $from = $requestedFrom;
        elseif ($requestedUntil > 0){
            if ($requestedUntil < 100)
                $count = $requestedUntil;
            else
                $from = $requestedUntil - 100;
        }
        
        $resultSet = Document::getPagesByLength('ASC', $from, $count);

        if (count($resultSet) === $count + 1) {
            $next_v = $from + $count;
            array_pop($resultSet);
        }
        
        $page = [
            'view_name' => 'ShortestPages',
            'title' => Languages::get('page', 'ShortestPages'),
            'data' => [
                'content' => [],
                'prev_ver' => $from > 1 ? $from - 1 : null,
                'next_ver' => $next_v ?? null
            ],
            'menus' => [],
            'customData' => []
        ];
        foreach ($resultSet as $k => $r) {
            $resultSet[$k]['forceShowNamespace'] = self::forceShowNamespace($r['namespace'], $r['title']);
        }

        $page['data']['content'] = $resultSet;

        return $page;
    }
}