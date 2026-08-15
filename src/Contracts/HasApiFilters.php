<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Spatie\QueryBuilder\AllowedFilter;

/**
 * Filters that are always allowed and documented when the model is queried
 * through the Spectacular QueryBuilder.
 */
interface HasApiFilters
{
    /**
     * @return list<AllowedFilter>
     */
    public static function getApiFilters(): array;
}
