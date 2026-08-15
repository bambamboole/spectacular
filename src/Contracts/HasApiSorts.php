<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Spatie\QueryBuilder\AllowedSort;

/**
 * Sorts that are always allowed and documented when the model is queried
 * through the Spectacular QueryBuilder.
 */
interface HasApiSorts
{
    /**
     * @return list<AllowedSort|string>
     */
    public static function getApiSorts(): array;
}
