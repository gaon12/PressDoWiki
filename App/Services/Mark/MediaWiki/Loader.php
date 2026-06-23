<?php
namespace PressDo\App\Services\Mark\MediaWiki;

require_once __DIR__.'/wikitext.php';

class Loader
{
    public static function loadMarkUp(string $content, array $options): array
    {
        \WikitextParser::init();
        $parser = new \WikitextParser($content);

        return ['html' => $parser->result ?? '', 'categories' => [], 'links' => []];
    }
}
