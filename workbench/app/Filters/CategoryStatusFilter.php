<?php
declare(strict_types=1);

namespace Workbench\App\Filters;

use Bambamboole\Spectacular\Contracts\DocumentsFilterSchema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Spatie\QueryBuilder\Filters\Filter;
use Workbench\App\Enums\CategoryStatus;

/**
 * @template TModel of Model
 *
 * @implements Filter<TModel>
 */
final class CategoryStatusFilter implements DocumentsFilterSchema, Filter
{
    /** @var list<CategoryStatus> */
    private array $statuses;

    public function __construct(CategoryStatus ...$statuses)
    {
        $this->statuses = array_values($statuses);
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $values = array_intersect(
            Arr::wrap($value),
            array_map(fn (CategoryStatus $status): string => $status->value, $this->statuses),
        );

        $query->whereIn($query->qualifyColumn('status'), $values);
    }

    public function filterSchema(): Type
    {
        return (new ArrayType)->setItems(
            (new StringType)->enum(array_map(fn (CategoryStatus $status): string => $status->value, $this->statuses)),
        );
    }

    public function filterDescription(string $name): string
    {
        return sprintf(
            'Filter by `%s`. Only %s are selectable.',
            $name,
            implode(', ', array_map(fn (CategoryStatus $status): string => "`{$status->value}`", $this->statuses)),
        );
    }
}
