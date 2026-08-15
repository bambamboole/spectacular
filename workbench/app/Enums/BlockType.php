<?php
declare(strict_types=1);

namespace Workbench\App\Enums;

enum BlockType: string
{
    case Text = 'text';
    case Image = 'image';
}
