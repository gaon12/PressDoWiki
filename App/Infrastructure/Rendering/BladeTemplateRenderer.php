<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Rendering;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use PressDo\App\Shared\Rendering\TemplateRenderer;
use RuntimeException;

/**
 * Small application adapter around Illuminate View.
 *
 * All paths are resolved when the adapter is created. Rendering therefore does
 * not depend on the process working directory, unlike the legacy view layer.
 */
final class BladeTemplateRenderer implements TemplateRenderer
{
    private readonly Factory $views;

    public function __construct(string $viewPath, string $cachePath)
    {
        $viewPath = self::existingDirectory($viewPath, 'Blade view');
        $cachePath = self::writableCacheDirectory($cachePath);

        $files = new Filesystem();
        $container = new Container();
        $events = new Dispatcher($container);
        $compiler = new BladeCompiler($files, $cachePath);

        $engines = new EngineResolver();
        $engines->register('blade', static fn(): CompilerEngine => new CompilerEngine($compiler, $files));

        $finder = new FileViewFinder($files, [$viewPath]);
        $this->views = new Factory($engines, $finder, $events);
        $this->views->setContainer($container);
    }

    public function render(string $view, array $data = []): string
    {
        if ($view === '') {
            throw new InvalidArgumentException('The Blade view name cannot be empty.');
        }

        return $this->views->make($view, $data)->render();
    }

    private static function existingDirectory(string $path, string $label): string
    {
        $resolved = realpath($path);
        if ($resolved === false || !is_dir($resolved)) {
            throw new InvalidArgumentException($label . ' directory does not exist: ' . $path);
        }

        return $resolved;
    }

    private static function writableCacheDirectory(string $path): string
    {
        if (!is_dir($path) && !mkdir($path, 0o775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create the Blade cache directory: ' . $path);
        }

        $resolved = realpath($path);
        if ($resolved === false || !is_writable($resolved)) {
            throw new RuntimeException('Blade cache directory is not writable: ' . $path);
        }

        return $resolved;
    }
}
