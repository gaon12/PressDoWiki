<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Models\Document;
use PressDo\App\Core\Controller;
use PressDo\App\Helpers\{Languages,Namespaces};

class UncategorizedPages extends Controller
{
    public function makeData(): array
    {
        $requestedFrom = (int) $this->request->queryString('from');
        $requestedUntil = (int) $this->request->queryString('until');
        $from = 1;
        $count = 100;
        $namespace = Namespaces::validate($requestedNamespace = $this->request->queryString('namespace')) ? $requestedNamespace : Namespaces::document();
        
        if ($requestedFrom > 0)
            $from = $requestedFrom;
        elseif ($requestedUntil > 0){
            if ($requestedUntil < 100)
                $count = $requestedUntil;
            else
                $from = $requestedUntil - 100;
        }

        $namespace = Namespaces::validate($requestedNamespace = $this->request->queryString('namespace')) ? $requestedNamespace : Namespaces::document();
        $resultSet = Document::getUncategorizedPages($namespace, $from, $count);

        if (count($resultSet) === $count + 1) {
            $next_v = $from + $count;
            array_pop($resultSet);
        }

        $page = [
            'view_name' => 'UncategorizedPages',
            'title' => Languages::get('page', 'UncategorizedPages'),
            'data' => [
                'namespace' => $namespace,
                'content' => [],
                'prev_ver' => $from > 1 ? $from - 1 : null,
                'next_ver' => $next_v ?? null
            ],
            'menus' => [],
            'customData' => []
        ];

        $page['data']['content'] = $resultSet;

        return $page;
    }
}