<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Types;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * Scramble ships anyOf/allOf but no oneOf, and only oneOf carries an OpenAPI
 * discriminator — which is what lets a client (or the API reference UI) pick
 * the matching variant schema from the value of a single property.
 */
final class OneOf extends Type
{
    /** @var list<Type|Reference> */
    public array $items = [];

    public function __construct()
    {
        parent::__construct('oneOf');
    }

    /**
     * @param  list<Type|Reference>  $items
     */
    public function setItems(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    public function serializeAs31(): mixed
    {
        return $this->withVariants(parent::serializeAs31(), array_map(fn (Type|Reference $item): mixed => $item->serializeAs31(), $this->items));
    }

    public function serializeAs32(): mixed
    {
        return $this->withVariants(parent::serializeAs32(), array_map(fn (Type|Reference $item): mixed => $item->serializeAs32(), $this->items));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<mixed>  $variants
     * @return array<string, mixed>
     */
    private function withVariants(array $schema, array $variants): array
    {
        unset($schema['type']);

        return [...$schema, 'oneOf' => $variants];
    }
}
