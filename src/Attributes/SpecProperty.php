<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Documents a laravel-data payload field. Takes precedence over the promoted
 * property's docblock, which stays a valid way to describe a field.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class SpecProperty
{
    public function __construct(
        public ?string $description = null,
        /**
         * HTML rendered next to the field, links included.
         */
        public ?string $tooltip = null,
    ) {}
}
