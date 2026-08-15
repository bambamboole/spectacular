<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Workbench\App\Enums\BlockType;

final class TextBlockData extends PageBlockData
{
    public function __construct(
        BlockType $type,
        /** Rich text of the block. */
        #[Max(1000)]
        public string $text,
    ) {
        parent::__construct($type);
    }
}
