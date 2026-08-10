<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark;

use RuntimeException;

/**
 * Typed relationships extracted from a rendered document.
 *
 * Every markup engine crosses this boundary before controllers or models see
 * its output. Missing relationship types therefore become empty collections
 * instead of undefined array keys.
 */
final readonly class MarkupLinks
{
    /**
     * @param list<string> $documents
     * @param list<string> $redirects
     * @param list<string> $includes
     * @param list<string> $files
     * @param array<string, list<string>> $categories
     */
    public function __construct(
        public array $documents = [],
        public array $redirects = [],
        public array $includes = [],
        public array $files = [],
        public array $categories = [],
    ) {}

    /**
     * @param mixed $value Loader-provided relationship data.
     * @param mixed $fallbackCategories Legacy top-level category data.
     */
    public static function fromLoaderValue(mixed $value, mixed $fallbackCategories = []): self
    {
        if ($value === null || $value === []) {
            $value = [];
        } elseif (!is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Markup loader links must be an object-shaped array.');
        }

        $categories = $value['category'] ?? $fallbackCategories;

        return new self(
            self::stringList($value['link'] ?? [], 'links.link'),
            self::stringList($value['redirect'] ?? [], 'links.redirect'),
            self::stringList($value['include'] ?? [], 'links.include'),
            self::stringList($value['file'] ?? [], 'links.file'),
            self::categoryMap($categories),
        );
    }

    public function hasAny(): bool
    {
        return $this->documents !== []
            || $this->redirects !== []
            || $this->includes !== []
            || $this->files !== []
            || $this->categories !== [];
    }

    public function firstRedirect(): ?string
    {
        return $this->redirects[0] ?? null;
    }

    /** @return list<string> */
    private static function stringList(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException("Markup loader {$field} must be a list.");
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new RuntimeException("Markup loader {$field} must contain non-empty strings.");
            }

            if (!in_array($item, $result, true)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @return array<string, list<string>> */
    private static function categoryMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('Markup loader categories must be an array.');
        }

        $result = [];
        if (array_is_list($value)) {
            foreach (self::stringList($value, 'categories') as $category) {
                $result[$category] = [];
            }

            return $result;
        }

        foreach ($value as $category => $classes) {
            if (!is_string($category) || $category === '') {
                throw new RuntimeException('Markup loader category names must be non-empty strings.');
            }

            $result[$category] = self::stringList($classes, "category classes for {$category}");
        }

        return $result;
    }
}
