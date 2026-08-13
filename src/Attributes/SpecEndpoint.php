<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Adds a tooltip to the endpoint. Its title and description stay with Scramble's
 * own `#[Endpoint]` attribute.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class SpecEndpoint
{
    public function __construct(
        /**
         * HTML rendered next to the endpoint, links included.
         */
        public ?string $tooltip = null,
    ) {}
}
