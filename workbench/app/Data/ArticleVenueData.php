<?php
declare(strict_types=1);

namespace Workbench\App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Data;

final class ArticleVenueData extends Data
{
    public function __construct(
        /** Street and house number. */
        #[Max(255)]
        public string $street,
        /** Two-letter ISO 3166-1 country code. */
        #[Size(2), MapInputName('country_code')]
        public string $countryCode,
        /** Additional address line, e.g. a building or floor. */
        #[Max(255), MapInputName('street_extra')]
        public ?string $streetExtra = null,
    ) {}
}
