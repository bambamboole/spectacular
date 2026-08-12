<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

enum CategoryStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
