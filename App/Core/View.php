<?php

declare(strict_types=1);

namespace PressDo\App\Core;

use InvalidArgumentException;
use Latte\Engine as Latte;
use PressDo\App\Helpers\Config;
use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;
use PressDo\App\Shared\Rendering\TemplateRenderer;
use RuntimeException;
use Throwable;

final class View
{
    private const FALLBACK_SKIN = 'pressdo';

    public Latte $latte;

    public Skin $skin;

    /** @var array<string, mixed> */
    public array $session;

    /** @var array<string, mixed> */
    public array $params;

    private readonly TemplateRenderer $templates;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(array $session = [], ?TemplateRenderer $templates = null)
    {
        $root = dirname(__DIR__, 2);

        $this->session = $session;
        $this->templates = $templates ?? new BladeTemplateRenderer(
            $root . '/resources/views',
            $root . '/var/cache/blade',
        );
    }

    /**
     * Initializes the legacy Latte renderer and selects a valid skin.
     *
     * Latte remains here only for layouts that have not moved to Blade yet.
     */
    public function renderInit(): void
    {
        $this->latte = new Latte();

        $this->latte->addFilter(
            'localdate',
            fn(int $timestamp): string => '<time datetime="' . gmdate('Y-m-d\TH:i:s', $timestamp) . '.000Z">' . date('Y-m-d H:i:s', $timestamp) . '</time>',
        );

        $this->latte->addFilter(
            'localreldate',
            fn(int $timestamp): string => '<time datetime="' . gmdate('Y-m-d\TH:i:s', $timestamp) . '.000Z">' . Controller::formatBefore($timestamp) . '</time>',
        );

        $this->latte->addFilter(
            'makeTitle',
            fn(string $title, string $namespace): string => Controller::makeTitle($namespace, $title),
        );

        $this->latte->setTempDirectory('../temp');
        $this->latte->addFilter('formatTime', fn(int $time): array => Controller::formatTime($time));

        $skinName = $this->resolveSkinName($this->requestedSkinName());
        $this->skin = new Skin($skinName, $this->loadSkinConfig($skinName));
    }

    public function renderPage(): string
    {
        $route = $this->routeData();
        $page = $route['page'];

        switch ($page) {
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
                $file = '../App/Views/layouts/' . $page . '/' . $route['menu'] . '.latte';
                break;
            case 'new_edit_request':
            case 'edit_request':
                $file = '../App/Views/layouts/edit.latte';
                if ($route['action'] === 'edit') {
                    break;
                }
                // The legacy route intentionally falls through for non-edit actions.
                // no break
            default:
                $file = '../App/Views/layouts/' . $page . '.latte';
        }

        $viewName = $this->viewName();
        if ($viewName === 'error') {
            $file = '../App/Views/layouts/error.latte';
        } elseif ($viewName === 'notfound') {
            $file = '../App/Views/layouts/notfound.latte';
        }

        $this->params['innerLayout'] = $this->latte->renderToString($file, $this->params);
        $this->params['body'] = $this->renderSkinLayout($this->skin->name);

        return $this->templates->render('frame', $this->params);
    }

    private function requestedSkinName(): string
    {
        $member = $this->session['member'] ?? null;
        if (is_array($member)) {
            $settings = $member['settings'] ?? null;
            if (is_array($settings)) {
                foreach (['skin_name', 'skin'] as $key) {
                    $skin = $settings[$key] ?? null;
                    if (is_string($skin) && $skin !== '') {
                        return $skin;
                    }
                }
            }
        }

        $default = Config::get('wiki.default_skin');

        return is_string($default) && $default !== '' ? $default : self::FALLBACK_SKIN;
    }

    /**
     * @return array{page: string, menu: string, action: string}
     */
    private function routeData(): array
    {
        $route = $this->params['uri_data'] ?? null;
        if (!is_array($route)) {
            throw new InvalidArgumentException('View parameter "uri_data" must be an array.');
        }

        $page = $route['page'] ?? null;
        if (!is_string($page) || $page === '') {
            throw new InvalidArgumentException('View route parameter "page" must be a non-empty string.');
        }

        $menu = $route['menu'] ?? '';
        $action = $route['action'] ?? '';

        return [
            'page' => $page,
            'menu' => is_string($menu) ? $menu : '',
            'action' => is_string($action) ? $action : '',
        ];
    }

    private function viewName(): string
    {
        $wiki = $this->params['wiki'] ?? null;
        if (!is_array($wiki)) {
            return '';
        }

        $page = $wiki['page'] ?? null;
        if (!is_array($page)) {
            return '';
        }

        $viewName = $page['view_name'] ?? null;

        return is_string($viewName) ? $viewName : '';
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

    /**
     * @return array<string, mixed>
     */
    private function loadSkinConfig(string $skinName): array
    {
        $json = file_get_contents($this->skinConfigPath($skinName));
        $config = $json === false ? null : json_decode($json, true);

        $skinConfig = [
            'js' => [],
            'additional_heads' => [],
            'body_classes' => [],
        ];

        if (!is_array($config)) {
            return $skinConfig;
        }

        // JSON objects should have string keys. Ignore numeric keys so every
        // caller receives the documented array<string, mixed> shape.
        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $skinConfig[$key] = $value;
            }
        }

        return $skinConfig;
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

    /**
     * @param array<string, mixed> $params
     */
    private function renderPhpTemplate(string $path, array $params): string
    {
        ob_start();
        try {
            extract($params, EXTR_SKIP);
            require $path;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
