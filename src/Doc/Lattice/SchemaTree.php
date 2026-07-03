<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Lattice;

use Lattice\Lattice\Attributes\AsComponent;
use Lattice\Lattice\Core\Components\Component;

#[AsComponent('spectacular.schema-tree')]
final class SchemaTree extends Component
{
    /** @var array<string, mixed> */
    public array $schema = [];

    /** @var array<string, mixed> */
    public array $components = [];

    public static function make(?string $key = null): static
    {
        return new self($key);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $components
     */
    public function forSchema(array $schema, array $components): static
    {
        $this->schema = $schema;
        $this->components = $components;

        return $this;
    }
}
