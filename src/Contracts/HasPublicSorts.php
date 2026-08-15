<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Contracts;

use Spatie\QueryBuilder\AllowedSort;

/**
 * Sorts that are always allowed when the model is queried through the
 * spectacular QueryBuilder — without every controller re-declaring them —
 * and documented on every operation querying the model.
 */
interface HasPublicSorts
{
    /**
     * @return list<AllowedSort|string>
     */
    public static function getSorts(): array;
}
