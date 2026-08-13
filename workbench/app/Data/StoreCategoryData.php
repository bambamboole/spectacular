<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Workbench\App\Enums\CategoryStatus;

final class StoreCategoryData extends Data
{
    public function __construct(
        #[SpecProperty(
            description: 'Display name of the category.',
            tooltip: 'Shown in navigation. Read the <a href="https://example.com/docs/categories">category guide</a>.',
        )]
        public string $name,
        /** Publication state of the category. Drafts stay hidden. */
        #[SpecProperty(tooltip: 'Only <code>published</code> categories appear in the storefront.')]
        public CategoryStatus $status = CategoryStatus::Draft,
        /** Whether the category is listed in public navigation. */
        #[MapInputName('is_visible')]
        public bool $isVisible = true,
        /** Category this one is nested under. */
        #[MapInputName('parent_id')]
        public ?int $parentId = null,
    ) {}
}
