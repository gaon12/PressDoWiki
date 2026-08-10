<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

use RuntimeException;

/**
 * Collects document relationships while markup is rendered.
 *
 * Rendering and backlink extraction share this object so a syntax feature
 * cannot accidentally display a link without also exposing its relationship to
 * the search and backlink layers.
 */
final class LinkCollection
{
    private const MAX_RELATIONSHIPS = 10_000;

    /**
     * @var array{
     *     link: list<string>,
     *     redirect: list<string>,
     *     include: list<string>,
     *     file: list<string>,
     *     category: array<string, list<string>>
     * }
     */
    private array $links = [
        'link' => [],
        'redirect' => [],
        'include' => [],
        'file' => [],
        'category' => [],
    ];

    /** @var array<string, true> */
    private array $seenDocuments = [];

    private int $relationshipCount = 0;

    public function addLink(string $document): void
    {
        $indexKey = "document\0" . $document;
        if (isset($this->seenDocuments[$indexKey])) {
            return;
        }

        $this->reserveRelationship();
        $this->seenDocuments[$indexKey] = true;
        $this->links['link'][] = $document;
    }

    public function addRedirect(string $document): void
    {
        if ($this->links['redirect'] === []) {
            $this->reserveRelationship();
            $this->links['redirect'][] = $document;
        }
    }

    public function addCategory(string $category): void
    {
        if (!array_key_exists($category, $this->links['category'])) {
            $this->reserveRelationship();
            $this->links['category'][$category] = [];
        }
    }

    /**
     * @return array{
     *     link: list<string>,
     *     redirect: list<string>,
     *     include: list<string>,
     *     file: list<string>,
     *     category: array<string, list<string>>
     * }
     */
    public function all(): array
    {
        return $this->links;
    }

    private function reserveRelationship(): void
    {
        if ($this->relationshipCount >= self::MAX_RELATIONSHIPS) {
            throw new RuntimeException('NamuMark documents cannot contain more than 10,000 unique relationships.');
        }

        ++$this->relationshipCount;
    }
}
