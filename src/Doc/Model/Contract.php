<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class Contract
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public string $role,
        public ?string $status,
        public ?string $mediaType,
        public array $schema,
        public ?string $title,
    ) {}
}
