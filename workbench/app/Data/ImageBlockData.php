<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Workbench\App\Enums\BlockType;

final class ImageBlockData extends PageBlockData
{
    public function __construct(
        BlockType $type,
        /** Source URL of the image. */
        public string $url,
        /** Caption shown below the image. */
        public ?string $caption = null,
    ) {
        parent::__construct($type);
    }
}
