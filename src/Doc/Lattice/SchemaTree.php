<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Lattice;

use Lattice\Lattice\Attributes\AsComponent;
use Lattice\Lattice\Core\Components\Component;

#[AsComponent('spectacular.schema-tree')]
final class SchemaTree extends Component
{
    /** @var array<string, mixed> */
    public array $document = [];

    public string $pointer = '#/';

    public static function make(?string $key = null): static
    {
        return new self($key);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function for(array $document, string $pointer): static
    {
        $this->document = $document;
        $this->pointer = $pointer;

        return $this;
    }
}
