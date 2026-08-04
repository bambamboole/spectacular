<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular;

enum PaginationMode: string
{
    case Default = 'default';
    case Simple = 'simple';
    case Cursor = 'cursor';
}
