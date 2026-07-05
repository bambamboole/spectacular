<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Lattice;

use InvalidArgumentException;
use Lattice\Lattice\Attributes\AsComponent;
use Lattice\Lattice\Core\Components\Component;

#[AsComponent('spectacular.api-reference')]
final class ApiReference extends Component
{
    /** @var array<string, mixed> */
    public array $spec = [];

    public ?string $url = null;

    public ?string $operation = null;

    /** @var list<string>|null */
    public ?array $tags = null;

    public bool $hideNav = false;

    public string $layout = 'sidebar';

    public ?string $defaultOperation = null;

    public bool $hideHeader = false;

    public ?string $title = null;

    public int $expandDepth = 0;

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

    public function operation(string $id): static
    {
        $this->operation = $id;

        return $this;
    }

    /**
     * @param  string|array<int, string>  $tags
     */
    public function tag(string|array $tags): static
    {
        $this->tags = is_array($tags) ? array_values($tags) : [$tags];

        return $this;
    }

    public function hideNav(bool $hide = true): static
    {
        $this->hideNav = $hide;

        return $this;
    }

    public function layout(string $layout): static
    {
        if (! in_array($layout, ['sidebar', 'stacked'], true)) {
            throw new InvalidArgumentException("Invalid layout [{$layout}]. Expected one of: sidebar, stacked.");
        }

        $this->layout = $layout;

        return $this;
    }

    public function defaultOperation(string $id): static
    {
        $this->defaultOperation = $id;

        return $this;
    }

    public function hideHeader(bool $hide = true): static
    {
        $this->hideHeader = $hide;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function expandDepth(int $depth): static
    {
        $this->expandDepth = $depth;

        return $this;
    }
}
