<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class ArticleSectionData extends Data
{
    /**
     * @param  list<ArticleSectionData>  $children
     */
    public function __construct(
        /** Heading shown above the section. */
        public string $heading,
        /** Whether the section starts collapsed. Defaults to false. */
        #[MapInputName('is_collapsed')]
        public bool $isCollapsed = false,
        /** Venue the section reports from. */
        public ?ArticleVenueData $venue = null,
        /** Sections nested below this one. */
        public array $children = [],
    ) {}
}
