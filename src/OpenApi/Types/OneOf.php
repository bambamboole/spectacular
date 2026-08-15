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

    private ?string $discriminatorProperty = null;

    /** @var array<string, string> */
    private array $discriminatorMapping = [];

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

    /**
     * @param  array<string, string>  $mapping  discriminator value => schema ref
     */
    public function setDiscriminator(string $propertyName, array $mapping): self
    {
        $this->discriminatorProperty = $propertyName;
        $this->discriminatorMapping = $mapping;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        unset($array['type']);

        return array_merge(
            $array,
            ['oneOf' => array_map(fn ($item) => $item->toArray(), $this->items)],
            $this->discriminatorProperty === null ? [] : [
                'discriminator' => array_filter([
                    'propertyName' => $this->discriminatorProperty,
                    'mapping' => $this->discriminatorMapping,
                ], fn ($value) => $value !== []),
            ],
        );
    }
}
