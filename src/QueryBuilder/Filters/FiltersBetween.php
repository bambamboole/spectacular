<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Inclusive range filter: the value carries exactly two comma-separated
 * bounds (`filter[created_at.between]=2026-01-01,2026-02-01`), anything else
 * is a query error — matching laravel-query-builder's 400 semantics. The
 * internal name is the column the range applies to.
 *
 * @template TModel of Model
 *
 * @implements Filter<TModel>
 */
final class FiltersBetween implements Filter
{
    public static function allowed(string $column, ?string $name = null): AllowedFilter
    {
        return AllowedFilter::custom($name ?? "{$column}.between", new self, $column);
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? array_values($value) : [$value];

        abort_unless(count($values) === 2, 400, "The {$property} between filter needs exactly two comma-separated values.");

        $query->whereBetween($query->qualifyColumn($property), [$values[0], $values[1]]);
    }
}
