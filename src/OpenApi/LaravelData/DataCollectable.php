<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

enum DataCollectable: string
{
    case Collection = DataCollection::class;
    case Paginated = PaginatedDataCollection::class;
    case CursorPaginated = CursorPaginatedDataCollection::class;

    public static function forClass(string $class): ?self
    {
        foreach (self::cases() as $case) {
            if (is_a($class, $case->value, true)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * A paginated collectable carries `links` and `meta` next to its items, so it
     * has nowhere to put them without a wrapper — laravel-data wraps it under
     * `data` even when wrapping is globally off.
     */
    public function isAlwaysWrapped(): bool
    {
        return $this !== self::Collection;
    }
}
