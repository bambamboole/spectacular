<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Spatie\QueryBuilder\AllowedFilter;

/**
 * Filters that are always allowed when the model is queried through the
 * spectacular QueryBuilder — without every controller re-declaring them —
 * and documented on every operation querying the model. Query scope filters
 * shared across endpoints are the intended use, but any AllowedFilter works.
 */
interface HasPublicFilters
{
    /**
     * @return list<AllowedFilter>
     */
    public static function getFilters(): array;
}
