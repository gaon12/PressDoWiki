<?php
namespace PressDo\App\Helpers\SearchEngines;

interface SearchEngineInterface
{
    public function getSearchResult(): array;
}
