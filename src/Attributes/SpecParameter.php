<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;
use Dedoc\Scramble\Attributes\MissingValue;

/**
 * Documents a single parameter of the endpoint, selected by name. Works for path
 * parameters as well as for the query parameters Spectacular generates itself.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
final readonly class SpecParameter
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        /**
         * HTML rendered next to the parameter, links included.
         */
        public ?string $tooltip = null,
        public mixed $default = new MissingValue,
    ) {}
}
