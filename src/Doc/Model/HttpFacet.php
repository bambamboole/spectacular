<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Model;

final readonly class HttpFacet implements Facet
{
    public function __construct(
        public string $method,
        public string $path,
        public ?string $operationId,
    ) {}
}
