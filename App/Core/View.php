<?php

namespace PressDo\App\Core;

use Latte\Engine as Latte;
use PressDo\App\Helpers\Config;
use RuntimeException;

class View
{
    private const FALLBACK_SKIN = 'pressdo';

    public Latte $latte;

    public object $skin;

    public array $session;

    public array $params;

    public function __construct(array $session = [])
    {
        $this->session = $session;
    }

    /**
     * Initialize Frontend.
     */
    public function renderInit(): void
    {
        $this->latte = new Latte();

        $this->latte->addFilter(
            'localdate',
            fn(int $t): string => '<time datetime="' . gmdate('Y-m-d\TH:i:s', $t) . '.000Z">' . date('Y-m-d H:i:s', $t) . '</time>'
        );

        $this->latte->addFilter(
            'localreldate',
            fn(int $t): string => '<time datetime="' . gmdate('Y-m-d\TH:i:s', $t) . '.000Z">' . Controller::formatBefore($t) . '</time>'
        );

        $this->latte->addFilter(
            'makeTitle',
            fn(string $title, string $namespace): string => Controller::makeTitle($namespace, $title)
        );

        $this->latte->setTempDirectory('../temp');
        $this->latte->addFilter('formatTime', fn($time) => Controller::formatTime($time));

        $this->skin = new \stdClass();
        $requestedSkin = $this->session['member']['settings']['skin_name']
            ?? $this->session['member']['settings']['skin']
            ?? Config::get('wiki.default_skin');
        $this->skin->name = $this->resolveSkinName((string) $requestedSkin);
        $this->skin->config = $this->loadSkinConfig($this->skin->name);
    }

    public function renderPage(): string
    {
        switch ($this->params['uri_data']['page']) {
            case 'LongestPages':
            case 'NeededPages':
            case 'OldPages':
            case 'OrphanedPages':
            case 'ShortestPages':
            case 'UncategorizedPages':
            case 'RandomPage':
                $file = '../App/Views/layouts/pagelist.latte';
                break;
            case 'admin':
            case 'member':
                $file = '../App/Views/layouts/' . $this->params['uri_data']['page'] . '/' . $this->params['uri_data']['menu'] . '.latte';
                break;
            case 'new_edit_request':
            case 'edit_request':
                $file = '../App/Views/layouts/edit.latte';
                if ($this->params['uri_data']['action'] == 'edit') {
                    break;
                }
                // no break
            default:
                $file = '../App/Views/layouts/' . $this->params['uri_data']['page'] . '.latte';
        }

        if ($this->params['wiki']['page']['view_name'] == 'error') {
            $file = '../App/Views/layouts/error.latte';
        } elseif ($this->params['wiki']['page']['view_name'] == 'notfound') {
            $file = '../App/Views/layouts/notfound.latte';
        }

        $this->params['innerLayout'] = $this->latte->renderToString($file, $this->params);
        $this->params['body'] = $this->renderSkinLayout($this->skin->name);

        return $this->latte->renderToString('../App/Views/frame.latte', $this->params);
    }

    private function resolveSkinName(string $skinName): string
    {
        if ($this->skinExists($skinName)) {
            return $skinName;
        }

        if ($this->skinExists(self::FALLBACK_SKIN)) {
            return self::FALLBACK_SKIN;
        }

        throw new RuntimeException('No usable skin found.');
    }

    private function skinExists(string $skinName): bool
    {
        return is_file($this->skinConfigPath($skinName)) && $this->findSkinLayoutPath($skinName) !== null;
    }

    private function loadSkinConfig(string $skinName): array
    {
        $json = file_get_contents($this->skinConfigPath($skinName));
        $config = $json === false ? null : json_decode($json, true);

        return array_replace([
            'js' => [],
            'additional_heads' => [],
            'body_classes' => [],
        ], is_array($config) ? $config : []);
    }

    private function skinConfigPath(string $skinName): string
    {
        return 'skins/' . $skinName . '/config.json';
    }

    private function renderSkinLayout(string $skinName): string
    {
        $path = $this->skinLayoutPath($skinName);
        if (str_ends_with($path, '.php')) {
            return $this->renderPhpTemplate($path, $this->params);
        }

        return $this->latte->renderToString($path, $this->params);
    }

    private function skinLayoutPath(string $skinName): string
    {
        $path = $this->findSkinLayoutPath($skinName);
        if ($path === null) {
            throw new RuntimeException('Skin layout not found.');
        }

        return $path;
    }

    private function findSkinLayoutPath(string $skinName): ?string
    {
        foreach (['layout.php', 'layout.latte'] as $filename) {
            $path = 'skins/' . $skinName . '/' . $filename;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function renderPhpTemplate(string $path, array $params): string
    {
        ob_start();
        try {
            extract($params, EXTR_SKIP);
            require $path;

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
