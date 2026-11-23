<?php

declare(strict_types=1);

namespace PressDo\App\Http\Security;

final class PermissionGrantPolicy
{
    private const PROTECTED = ['admin', 'developer', 'grant', 'config', 'hideip'];

    /**
     * @param list<string> $actor
     * @param list<string> $target
     * @param list<string> $catalog
     * @return list<string>
     */
    public static function manageable(array $actor, array $target, array $catalog, bool $sameAccount): array
    {
        if ($sameAccount) {
            return [];
        }
        if (in_array('developer', $actor, true)) {
            return $catalog;
        }
        if (!in_array('grant', $actor, true) || array_intersect($target, self::PROTECTED) !== []) {
            return [];
        }

        return array_values(array_diff(array_intersect($catalog, $actor), self::PROTECTED));
    }
}
