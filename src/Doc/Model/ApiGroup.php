<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class ApiGroup
{
    /**
     * @param  list<string>  $operationIds
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public array $operationIds,
    ) {}
}
