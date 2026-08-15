<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Declares the concrete variants of an abstract property-morphable data class
 * for the OpenAPI document: discriminator value => variant class. The runtime
 * mapping stays in the class's morph() — this attribute states it declaratively
 * so the generator can emit a oneOf with a discriminator instead of guessing.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SpecOneOf
{
    /**
     * @param  array<string, class-string>  $mapping
     */
    public function __construct(public array $mapping) {}
}
