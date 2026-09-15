<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

interface EndpointDefinition
{
    /** @return list<Endpoint> */
    public function endpoints(): array;

    /** @return array<string, array<string, mixed>> */
    public function schemas(): array;
}
