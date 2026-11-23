<?php

declare(strict_types=1);

namespace PressDo\App\Core;

use InvalidArgumentException;
use PressDo\App\Helpers\Config;
use PressDo\App\Helpers\RouteControllerResolver;
use PressDo\App\Infrastructure\Rendering\BladeTemplateRenderer;
use PressDo\App\Shared\Rendering\TemplateRenderer;
use RuntimeException;
use Throwable;

final class View
{
    private const FALLBACK_SKIN = 'pressdo';

    public Skin $skin;

    /** @var array<string, mixed> */
    public array $session;

    /** @var array<string, mixed> */
    public array $params;

    private readonly TemplateRenderer $templates;

    private readonly string $viewRoot;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(array $session = [], ?TemplateRenderer $templates = null)
    {
        $this->session = $session;
        $this->viewRoot = ProjectPaths::resources('views');
        $this->templates = $templates ?? new BladeTemplateRenderer(
            $this->viewRoot,
            ProjectPaths::variable('cache/blade'),
        );
    }

    public function renderInit(): void
    {
        $skinName = $this->resolveSkinName($this->requestedSkinName());
        $this->skin = new Skin($skinName, $this->loadSkinConfig($skinName));
    }

    public function renderPage(): string
    {
        $this->params['query'] ??= new Request();
        $this->params['innerLayout'] = $this->templates->render($this->pageTemplate(), $this->params);
        $this->params['body'] = $this->renderSkinLayout($this->skin->name);

        return $this->templates->render('frame', $this->params);
    }

    private function pageTemplate(): string
    {
        $route = $this->routeData();
        $view = $this->viewName();

        if (in_array($view, ['wiki', 'error', 'notfound'], true)) {
            return $view;
        }

        if (in_array($view, ['thread_list_close', 'edit_request_close'], true)) {
            return 'thread_list';
        }

        return match ($route['page']) {
            'admin', 'member' => $route['page'] . '.' . $route['menu'],
            'longest_pages', 'needed_pages', 'old_pages', 'orphaned_pages',
            'shortest_pages', 'uncategorized_pages', 'random_page' => 'pagelist',
            'new_edit_request' => 'edit',
            'edit_request' => $route['action'] === 'edit' ? 'edit' : 'edit_request',
            default => $route['page'],
        };
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

        $rawPage = $route['page'] ?? null;
        $page = is_string($rawPage) ? RouteControllerResolver::pageName($rawPage) : null;
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
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $skinName) !== 1) {
            return false;
        }

        return is_file($this->skinConfigPath($skinName))
            && ($this->builtInSkinView($skinName) !== null || $this->findSkinLayoutPath($skinName) !== null);
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
        return ProjectPaths::public('skins/' . $skinName . '/config.json');
    }

    private function renderSkinLayout(string $skinName): string
    {
        $builtInView = $this->builtInSkinView($skinName);
        if ($builtInView !== null) {
            return $this->templates->render($builtInView, $this->params);
        }

        $path = $this->findSkinLayoutPath($skinName);
        if ($path === null) {
            throw new RuntimeException('Skin layout not found.');
        }

        return $this->renderPhpTemplate($path, $this->params);
    }

    private function findSkinLayoutPath(string $skinName): ?string
    {
        $path = ProjectPaths::public('skins/' . $skinName . '/layout.php');

        return is_file($path) ? $path : null;
    }

    private function builtInSkinView(string $skinName): ?string
    {
        if (str_contains($skinName, '.')) {
            return null;
        }

        $path = $this->viewRoot . '/skins/' . $skinName . '.blade.php';

        return is_file($path) ? 'skins.' . $skinName : null;
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
