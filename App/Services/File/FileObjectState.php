<?php

declare(strict_types=1);

namespace PressDo\App\Services\File;

enum FileObjectState: string
{
    case Current = 'current';
    case Legacy = 'legacy';
    case Missing = 'missing';
}
