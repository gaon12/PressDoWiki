<?php

declare(strict_types=1);

namespace PressDo\App\Services\Mark\NamuMark;

/**
 * Collects document relationships while markup is rendered.
 *
 * Rendering and backlink extraction share this object so a syntax feature
 * cannot accidentally display a link without also exposing its relationship to
 * the search and backlink layers.
 */
final class LinkCollection
{
    /**
     * @var array{
     *     link: list<string>,
     *     redirect: list<string>,
     *     include: list<string>,
     *     file: list<string>,
     *     category: list<string>
     * }
     */
    private array $links = [
        'link' => [],
        'redirect' => [],
        'include' => [],
        'file' => [],
        'category' => [],
    ];

    public function addLink(string $document): void
    {
        if (!in_array($document, $this->links['link'], true)) {
            $this->links['link'][] = $document;
        }
    }

    /**
     * @return array{
     *     link: list<string>,
     *     redirect: list<string>,
     *     include: list<string>,
     *     file: list<string>,
     *     category: list<string>
     * }
     */
    public function all(): array
    {
        return $this->links;
    }
}
