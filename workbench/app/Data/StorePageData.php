<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Data;

final class StorePageData extends Data
{
    /**
     * @param  array<int, PageBlockData>  $blocks
     */
    public function __construct(
        /** Page headline. */
        public string $title,
        /** Content blocks in display order. */
        public array $blocks = [],
    ) {}
}
