<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class Param
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $name,
        public string $location,
        public bool $required,
        public bool $deprecated,
        public ?string $description,
        public array $schema,
    ) {}
}
