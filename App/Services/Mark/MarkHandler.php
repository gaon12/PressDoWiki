<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark;

use PressDo\App\Helpers\Config;
use RuntimeException;

class MarkHandler
{
    private const MARK_ALIASES = [
        'Namumark' => 'NamuMark',
        'namumark' => 'NamuMark',
    ];

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function load(string $content, array $options): array
    {
        $mark = Config::get('wiki.mark');
        if (!is_string($mark) || $mark === '') {
            throw new RuntimeException('The configured markup language must be a non-empty string.');
        }

        $mark = self::MARK_ALIASES[$mark] ?? $mark;
        $markClassName = 'PressDo\App\Services\Mark\\' . $mark . '\Loader';

        if (!class_exists($markClassName) || !method_exists($markClassName, 'loadMarkUp')) {
            throw new RuntimeException('Unsupported markup loader: ' . $mark);
        }

        $result = $markClassName::loadMarkUp($content, $options);
        if (!is_array($result)) {
            throw new RuntimeException('Markup loaders must return an array.');
        }

        $output = [];
        foreach ($result as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Markup loader result keys must be strings.');
            }

            $output[$key] = $value;
        }

        return $output;
    }
}
