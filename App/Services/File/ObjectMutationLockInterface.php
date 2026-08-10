<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

use Closure;
use PressDo\App\Services\Uploaders\ObjectKey;

interface ObjectMutationLockInterface
{
    /**
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function synchronized(ObjectKey $key, Closure $operation): mixed;
}
