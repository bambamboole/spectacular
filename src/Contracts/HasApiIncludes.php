<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Spatie\QueryBuilder\AllowedInclude;

/**
 * Includes that are always allowed and documented when the model is queried
 * through the Spectacular QueryBuilder.
 */
interface HasApiIncludes
{
    /**
     * @return list<AllowedInclude|string>
     */
    public static function getApiIncludes(): array;
}
