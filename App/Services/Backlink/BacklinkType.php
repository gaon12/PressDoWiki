<?php

declare(strict_types=1);

namespace PressDo\App\Services\Backlink;

/**
 * Relationship types stored in the wiki's backlink index.
 *
 * Keeping these values in an enum prevents markup engines and persistence code
 * from silently inventing incompatible type strings.
 */
enum BacklinkType: string
{
    case Category = 'category';
    case File = 'file';
    case Include = 'include';
    case Link = 'link';
    case Redirect = 'redirect';
}
