<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * A custom query builder filter implementing this contract documents itself:
 * the OpenAPI generator uses the returned schema and description instead of
 * the generic custom-filter fallback. An ArrayType schema is serialized as a
 * comma-separated list (`style: form`, `explode: false`), matching how
 * laravel-query-builder splits filter values.
 */
interface DocumentsFilterSchema
{
    public function filterSchema(): Type;

    public function filterDescription(string $name): ?string;
}
