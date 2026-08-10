<?php

declare(strict_types=1);

namespace PressDo\App\Models;

use ErrorException;
use PDO;
use PDOException;
use PressDo\App\Core\Controller;
use PressDo\App\Services\Backlink\BacklinkTarget;
use PressDo\App\Services\Backlink\BacklinkType;
use PressDo\App\Services\Backlink\PdoBacklinkIndex;
use PressDo\App\Services\Mark\MarkupLinks;
use UnexpectedValueException;

final class Backlink extends \PressDo\App\Core\Model
{
    /**
     * Get backlinks of document
     *
     * @return array<string, list<array{title: string, namespace: string, type: string, total_count: int|string}>>
     */
    public static function get(string $namespace, string $title, string $target_ns, ?string $type = null): array
    {
        $db = self::db();
        $params = [$namespace, $title, $target_ns];

        if ($type !== null) {
            $typstr = ' AND links.type=?';
            array_push($params, $type);
        } else {
            $typstr = '';
        }

        try {
            $c = $db->prepare('SELECT document.title as `title`, document.namespace as `namespace`, links.type as `type`, COUNT(*) OVER() AS total_count FROM `links`,`document` WHERE links.from_uuid = document.uuid AND links.namespace=? AND links.title=? AND document.namespace=?' . $typstr . ' LIMIT 100');
            $c->execute($params);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage() . ': 역링크 조회 중 오류 발생');
        }
        $grouped = [];
        foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $normalized = self::backlinkRow($row);
            $grouped[$normalized['title']][] = $normalized;
        }

        return $grouped;
    }

    /**
     * Count backlinks in each namespace
     * @return list<array{namespace: string, cnt: int|string}>
     */
    public static function count(string $namespace, string $title): array
    {
        $db = self::db();

        try {
            $c = $db->prepare('SELECT document.namespace as `namespace`, COUNT(from_uuid) as cnt FROM `links`,`document` WHERE links.from_uuid = document.uuid AND links.namespace=? AND links.title=? GROUP BY document.namespace');
            $c->execute([$namespace, $title]);
        } catch (PDOException $err) {
            throw new ErrorException($err->getMessage() . ': 역링크 개수 확인 중 오류 발생');
        }
        $counts = [];
        foreach ($c->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new UnexpectedValueException('A backlink count row must be an array.');
            }

            $namespace = $row['namespace'] ?? null;
            $count = $row['cnt'] ?? null;
            if (!is_string($namespace) || (!is_int($count) && !is_string($count))) {
                throw new UnexpectedValueException('A backlink count row contains invalid values.');
            }

            $counts[] = ['namespace' => $namespace, 'cnt' => $count];
        }

        return $counts;
    }

    /**
     * Replace the complete backlink index produced by the markup engine.
     *
     * Empty link collections are intentional: they remove relationships left
     * behind by an older revision of the document.
     */
    public static function update(string $uuid, MarkupLinks $links): void
    {
        $binaryUuid = self::uuid2bin($uuid);

        try {
            (new PdoBacklinkIndex(self::db()))->replace($binaryUuid, self::targets($links));
        } catch (PDOException $error) {
            throw new ErrorException($error->getMessage() . ': 링크 갱신 중 오류 발생', previous: $error);
        }
    }

    /** @return list<BacklinkTarget> */
    private static function targets(MarkupLinks $links): array
    {
        $redirect = $links->firstRedirect();
        if ($redirect !== null) {
            return [self::target($redirect, BacklinkType::Redirect)];
        }

        $targets = [];
        foreach ($links->documents as $title) {
            $targets[] = self::target($title, BacklinkType::Link);
        }
        foreach ($links->files as $title) {
            $targets[] = self::target($title, BacklinkType::File);
        }
        foreach ($links->includes as $title) {
            $targets[] = self::target($title, BacklinkType::Include);
        }
        foreach (array_keys($links->categories) as $title) {
            $targets[] = new BacklinkTarget('분류', $title, BacklinkType::Category);
        }

        return $targets;
    }

    private static function target(string $fullTitle, BacklinkType $type): BacklinkTarget
    {
        $parts = Controller::parseTitle($fullTitle);
        $namespace = $parts[0] ?? null;
        $title = $parts[1] ?? null;
        if (!is_string($namespace) || !is_string($title)) {
            throw new UnexpectedValueException('A parsed document title must contain a namespace and title.');
        }

        return new BacklinkTarget($namespace, $title, $type);
    }

    /**
     * @param mixed $row Database-provided row.
     * @return array{title: string, namespace: string, type: string, total_count: int|string}
     */
    private static function backlinkRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new UnexpectedValueException('A backlink row must be an array.');
        }

        $title = $row['title'] ?? null;
        $namespace = $row['namespace'] ?? null;
        $type = $row['type'] ?? null;
        $totalCount = $row['total_count'] ?? null;
        if (
            !is_string($title)
            || !is_string($namespace)
            || !is_string($type)
            || (!is_int($totalCount) && !is_string($totalCount))
        ) {
            throw new UnexpectedValueException('A backlink row contains invalid values.');
        }

        return [
            'title' => $title,
            'namespace' => $namespace,
            'type' => $type,
            'total_count' => $totalCount,
        ];
    }
}
