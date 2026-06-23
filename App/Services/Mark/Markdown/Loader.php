<?php
namespace PressDo\App\Services\Mark\Markdown;

require_once __DIR__.'/Parsedown.php';

class Loader
{
    public static function loadMarkUp(string $content, array $options): array
    {
        $parsedown = new \Parsedown();

        return ['html' => $parsedown->text($content), 'categories' => []];
    }
}
