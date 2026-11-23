<?php
namespace PressDo\App\Controllers\Pages;

use PressDo\App\Core\Controller;
use PressDo\App\Helpers\{Languages,Namespaces,DefaultConfig};

class Search extends Controller
{
    private const SEARCH_TARGETS = [
        'title_content',
        'title',
        'content',
        'raw'
    ];

    public function makeData(): array
    {
        $keyword = $this->request->queryString('q');
        $requestedNamespace = $this->request->queryString('namespace');
        $namespace = Namespaces::validate($requestedNamespace) ? $requestedNamespace : null;
        $target = $this->request->queryString('target');
        if (!in_array($target, self::SEARCH_TARGETS, true)) {
            $target = 'title_content';
        }

        $searchData = [];
        if ($keyword !== '') {
            foreach ($this->searchQuery($keyword, $target, $namespace) as $result) {
                $excerpt = mb_substr($result['text'], 0, 256);
                $highlight = '<span class="search-highlight">' . htmlspecialchars($keyword) . '</span>';
                $searchData[] = [
                    'document' => [
                        'namespace' => $result['namespace'],
                        'title' => $result['title'],
                        'forceShowNamespace' => self::forceShowNamespace($result['namespace'], $result['title']),
                    ],
                    'content' => implode($highlight, array_map('htmlspecialchars', explode($keyword, $excerpt))),
                ];
            }
        }

        $page = [
            'view_name' => 'search',
            'title' => Languages::get('page', 'Search'),
            'data' => [
                'body' => [
                    'query' => $keyword,
                    'target' => $target,
                    'namespace' => $namespace
                ],
                'namespaces' => Namespaces::all(),
                'page' => 0,
                'result' => $searchData,
                'resultCount' => count($searchData),
                'period' => 0
            ]
        ];
        return $page;
    }

    /**
     * Search with designated search engine
     * @param string $keyword
     * @param string $target
     * @param mixed $namespace
     * @return array
     */
    private function searchQuery(string $keyword, string $target, ?string $namespace): array
    {
        $engine = (new \ReflectionClass('PressDo\App\Helpers\SearchEngines\\'.DefaultConfig::get('searchengine.type')))->newInstance();
        $engine->keyword = $keyword;
        $engine->target = $target;
        $engine->namespace = $namespace;
        return $engine->getSearchResult();
    }
}