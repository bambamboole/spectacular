<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\Spectacular\Contracts\HasPublicFilters;
use Bambamboole\Spectacular\Contracts\HasPublicSorts;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

/**
 * @template TModel of Model
 *
 * @extends SpatieQueryBuilder<TModel>
 */
class QueryBuilder extends SpatieQueryBuilder
{
    /** @var list<AllowedFilter>|null */
    private ?array $publicFilters = null;

    /** @var list<AllowedSort>|null */
    private ?array $publicSorts = null;

    private bool $publicFiltersMerged = false;

    private bool $publicSortsMerged = false;

    /** @var list<string> */
    private array $appliedPublicFilterNames = [];

    /** @var list<string> */
    private array $appliedPublicSortNames = [];

    /**
     * Filters a HasPublicFilters model declares are always allowed: an explicit
     * call merges them in (its own declaration wins over a public filter of the
     * same name), so spatie validates and applies everything in one place.
     */
    public function allowedFilters(AllowedFilter|string ...$filters): static
    {
        $this->publicFiltersMerged = true;

        $declared = array_map(
            fn (AllowedFilter|string $filter): AllowedFilter => $filter instanceof AllowedFilter
                ? $filter
                : AllowedFilter::partial($filter),
            array_values($filters),
        );
        $declaredNames = array_map(fn (AllowedFilter $filter): string => $filter->getName(), $declared);
        $missing = array_filter(
            $this->publicFilters(),
            fn (AllowedFilter $filter): bool => ! in_array($filter->getName(), $declaredNames, true),
        );

        return parent::allowedFilters(...$declared, ...$missing);
    }

    public function allowedSorts(AllowedSort|string ...$sorts): static
    {
        $this->publicSortsMerged = true;

        $declared = array_map(
            fn (AllowedSort|string $sort): AllowedSort => $sort instanceof AllowedSort
                ? $sort
                : AllowedSort::field(ltrim($sort, '-')),
            array_values($sorts),
        );
        $declaredNames = array_map(fn (AllowedSort $sort): string => $sort->getName(), $declared);
        $missing = array_filter(
            $this->publicSorts(),
            fn (AllowedSort $sort): bool => ! in_array($sort->getName(), $declaredNames, true),
        );

        return parent::allowedSorts(...$declared, ...$missing);
    }

    /**
     * A forwarded call may execute the query, so pending public declarations are
     * applied first. Validation has to wait: the forwarded call may as well be a
     * chain method with an explicit allowedFilters()/allowedSorts() still to come,
     * which would wrongly reject the filters that call is about to declare.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->applyPublicDeclarations(validate: false);

        return parent::__call($name, $arguments);
    }

    /**
     * @param  non-empty-list<PaginationMode>  $modes
     * @return Paginator<int, TModel>|LengthAwarePaginator<int, TModel>|CursorPaginator<int, TModel>
     */
    public function apiPaginate(
        array $modes = [PaginationMode::Default],
        int $max = 100,
    ): Paginator|LengthAwarePaginator|CursorPaginator {
        $this->applyPublicDeclarations(validate: true);
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

    /**
     * apiPaginate() is a terminal call, so it validates the request against the
     * public declarations; a forwarded call cannot (see __call).
     */
    private function applyPublicDeclarations(bool $validate): void
    {
        $this->applyUndeclaredPublicFilters($validate);
        $this->applyUndeclaredPublicSorts($validate);
    }

    private function applyUndeclaredPublicFilters(bool $validate): void
    {
        if ($this->publicFiltersMerged || ($filters = $this->publicFilters()) === []) {
            return;
        }

        $this->allowedFilters = collect($filters);

        if ($validate) {
            $this->ensureAllFiltersExist();
        }

        if ($this->appliedPublicFilterNames === []) {
            $this->addFiltersToQuery();
            $this->appliedPublicFilterNames = array_map(
                fn (AllowedFilter $filter): string => $filter->getName(),
                $filters,
            );
        }
    }

    private function applyUndeclaredPublicSorts(bool $validate): void
    {
        if ($this->publicSortsMerged || ($sorts = $this->publicSorts()) === []) {
            return;
        }

        $this->allowedSorts = collect($sorts);

        if ($validate) {
            $this->ensureAllSortsExist();
        }

        if ($this->appliedPublicSortNames === []) {
            $this->addRequestedSortsToQuery();
            $this->appliedPublicSortNames = array_map(
                fn (AllowedSort $sort): string => $sort->getName(),
                $sorts,
            );
        }
    }

    /**
     * A public filter a forwarded call already applied must not apply again when
     * a later explicit allowedFilters() call re-processes the merged set.
     */
    #[\Override]
    protected function addFiltersToQuery(): void
    {
        if ($this->appliedPublicFilterNames === []) {
            parent::addFiltersToQuery();

            return;
        }

        $declared = $this->allowedFilters;
        $this->allowedFilters = $declared
            ->reject(fn (AllowedFilter $filter): bool => in_array($filter->getName(), $this->appliedPublicFilterNames, true))
            ->values();
        parent::addFiltersToQuery();
        $this->allowedFilters = $declared;
    }

    #[\Override]
    protected function addRequestedSortsToQuery(): void
    {
        if ($this->appliedPublicSortNames === []) {
            parent::addRequestedSortsToQuery();

            return;
        }

        $declared = $this->allowedSorts;
        $this->allowedSorts = $declared
            ->reject(fn (AllowedSort $sort): bool => in_array($sort->getName(), $this->appliedPublicSortNames, true))
            ->values();
        parent::addRequestedSortsToQuery();
        $this->allowedSorts = $declared;
    }

    /**
     * @return list<AllowedFilter>
     */
    private function publicFilters(): array
    {
        if ($this->publicFilters !== null) {
            return $this->publicFilters;
        }

        $model = $this->getEloquentBuilder()->getModel();

        if (! $model instanceof HasPublicFilters) {
            return $this->publicFilters = [];
        }

        return $this->publicFilters = $model::getFilters();
    }

    /**
     * @return list<AllowedSort>
     */
    private function publicSorts(): array
    {
        if ($this->publicSorts !== null) {
            return $this->publicSorts;
        }

        $model = $this->getEloquentBuilder()->getModel();

        if (! $model instanceof HasPublicSorts) {
            return $this->publicSorts = [];
        }

        return $this->publicSorts = array_map(
            fn (AllowedSort|string $sort): AllowedSort => $sort instanceof AllowedSort
                ? $sort
                : AllowedSort::field(ltrim($sort, '-')),
            $model::getSorts(),
        );
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
