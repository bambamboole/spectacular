<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Adds Spectacular-specific documentation to an endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class SpecEndpoint
{
    public function __construct(
        /**
         * HTML rendered next to the endpoint, links included.
         */
        public ?string $tooltip = null,
        public bool $internal = false,
    ) {}
}
