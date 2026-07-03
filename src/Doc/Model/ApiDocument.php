<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class ApiDocument
{
    /**
     * @param  array<string, mixed>  $info
     * @param  list<array<string, mixed>>  $servers
     * @param  list<ApiGroup>  $groups
     * @param  list<Operation>  $operations
     * @param  array<string, mixed>  $components
     */
    public function __construct(
        public string $format,
        public string $formatVersion,
        public array $info,
        public array $servers,
        public array $groups,
        public array $operations,
        public array $components,
    ) {}
}
