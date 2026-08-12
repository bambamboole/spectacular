<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Data;

final class ArticleAuthorData extends Data
{
    public function __construct(
        /** Display name of the author. */
        public string $name,
        /** Contact address, omitted from public output. */
        public ?string $email = null,
    ) {}
}
