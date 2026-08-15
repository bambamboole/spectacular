<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class StoreArticleData extends Data
{
    /**
     * @param  list<StoreArticleData>  $translations
     * @param  list<ArticleSectionData>  $sections
     */
    public function __construct(
        /** Headline of the article. */
        public string $title,
        /** Author of the article. Optional, yet its own name is mandatory. */
        public ?ArticleAuthorData $author = null,
        /** Teaser shown in listings. */
        public ?string $summary = null,
        /** Whether the article is publicly visible. Defaults to false. */
        #[MapInputName('is_published')]
        public bool $isPublished = false,
        /** Translations of this article, nesting the same shape. */
        public array $translations = [],
        /** Sections structuring the article body. */
        public array $sections = [],
    ) {}
}
