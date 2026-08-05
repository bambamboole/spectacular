<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

/**
 * @template TModel of Model
 *
 * @extends SpatieQueryBuilder<TModel>
 */
class QueryBuilder extends SpatieQueryBuilder
{
    /**
     * @param  non-empty-list<PaginationMode>  $modes
     * @return Paginator<int, TModel>|LengthAwarePaginator<int, TModel>|CursorPaginator<int, TModel>
     */
    public function apiPaginate(
        array $modes = [PaginationMode::Default],
        int $max = 100,
    ): Paginator|LengthAwarePaginator|CursorPaginator {
        $mode = $this->selectedPaginationMode($modes);
        $perPage = $this->selectedPerPage($max);

        return (match ($mode) {
            PaginationMode::Default => $this->getEloquentBuilder()->paginate($perPage),
            PaginationMode::Simple => $this->getEloquentBuilder()->simplePaginate($perPage),
            PaginationMode::Cursor => $this->getEloquentBuilder()->cursorPaginate($perPage),
        })->withQueryString();
    }

    /**
     * @param  non-empty-list<PaginationMode>  $modes
     */
    private function selectedPaginationMode(array $modes): PaginationMode
    {
        $value = $this->request->header('x-pagination', $modes[0]->value);
        $mode = PaginationMode::tryFrom($value);

        if ($mode === null || ! in_array($mode, $modes, true)) {
            throw ValidationException::withMessages([
                'x-pagination' => 'The selected pagination mode is invalid.',
            ]);
        }

        return $mode;
    }

    private function selectedPerPage(int $max): int
    {
        $value = $this->request->query('per_page');

        if ($value === null) {
            return max(1, min($this->getEloquentBuilder()->getModel()->getPerPage(), $max));
        }

        $perPage = filter_var($value, FILTER_VALIDATE_INT);

        if ($perPage === false) {
            throw ValidationException::withMessages([
                'per_page' => 'The per page field must be an integer.',
            ]);
        }

        return max(1, min($perPage, $max));
    }
}
