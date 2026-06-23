<?php
namespace PressDo\App\Services\Mark\BBCode;

require_once __DIR__.'/Parser.php';

class Loader
{
    public static function loadMarkUp(string $content, array $options): array
    {
        $parser = new \JBBCode\Parser();
        $parser->addCodeDefinitionSet(new \JBBCode\DefaultCodeDefinitionSet());
        $parser->parse($content);

        return ['html' => $parser->getAsHtml(), 'categories' => []];
    }
}
