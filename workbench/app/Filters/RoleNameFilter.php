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

/**
 * @template TModel of Model
 *
 * @implements Filter<TModel>
 */
final class RoleNameFilter implements DocumentsFilterSchema, Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $names = array_filter(Arr::wrap($value), fn (mixed $name): bool => is_string($name) && $name !== '');

        if ($names === []) {
            return;
        }

        $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', $names));
    }

    public function filterSchema(): Type
    {
        return (new ArrayType)->setItems(new StringType);
    }

    public function filterDescription(string $name): string
    {
        return 'Only users holding any of the given role names.';
    }
}
