<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class Operation
{
    /**
     * @param  list<string>  $tags
     * @param  list<ParamGroup>  $paramGroups
     * @param  list<Contract>  $responses
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public string $id,
        public OperationKind $kind,
        public string $title,
        public ?string $summary,
        public ?string $description,
        public array $tags,
        public bool $deprecated,
        public array $paramGroups,
        public array $responses,
        public ?Facet $facet,
        public array $extensions = [],
    ) {}
}
