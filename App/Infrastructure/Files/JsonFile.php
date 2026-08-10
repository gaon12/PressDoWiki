<?php

declare(strict_types=1);

namespace PressDo\App\Infrastructure\Files;

use JsonException;
use RuntimeException;

/** Strict JSON file loading with predictable result shapes. */
final class JsonFile
{
    /** @return array<string, mixed> */
    public static function readObject(string $path): array
    {
        $decoded = self::decode($path);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new RuntimeException("JSON file must contain an object: {$path}");
        }

        $object = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException("JSON object keys must be strings: {$path}");
            }

            $object[$key] = $value;
        }

        return $object;
    }

    /** @return list<string> */
    public static function readStringList(string $path): array
    {
        $decoded = self::decode($path);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException("JSON file must contain a list: {$path}");
        }

        $values = [];
        foreach ($decoded as $value) {
            if (!is_string($value) || $value === '') {
                throw new RuntimeException("JSON list must contain non-empty strings: {$path}");
            }

            $values[] = $value;
        }

        return $values;
    }

    private static function decode(string $path): mixed
    {
        if (!is_file($path)) {
            throw new RuntimeException("JSON file does not exist: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("JSON file cannot be read: {$path}");
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("JSON file is invalid: {$path}", previous: $error);
        }
    }
}
