<?php
namespace PressDo\App\Services\Mark;

use PressDo\App\Helpers\DefaultConfig;
use RuntimeException;

class MarkHandler
{
    private const MARK_ALIASES = [
        'Namumark' => 'MediaWiki',
        'NamuMark' => 'MediaWiki',
        'namumark' => 'MediaWiki',
    ];

    public static function load(string $content, array $options): array
    {
        $mark = (string) DefaultConfig::get('wiki.mark');
        $mark = self::MARK_ALIASES[$mark] ?? $mark;
        $markClassName = 'PressDo\App\Services\Mark\\'.$mark.'\Loader';

        if (!class_exists($markClassName) || !method_exists($markClassName, 'loadMarkUp')) {
            throw new RuntimeException('Unsupported markup loader: '.$mark);
        }

        return $markClassName::loadMarkUp($content, $options);
    }
}
