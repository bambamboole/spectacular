<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Lattice;

use Lattice\Lattice\Attributes\AsComponent;
use Lattice\Lattice\Core\Components\Component;

#[AsComponent('spectacular.api-reference')]
final class ApiReference extends Component
{
    /** @var array<string, mixed> */
    public array $spec = [];

    public ?string $url = null;

    public static function make(?string $key = null): static
    {
        return new self($key);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    public function spec(array $spec): static
    {
        $this->spec = $spec;

        return $this;
    }

    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }
}
