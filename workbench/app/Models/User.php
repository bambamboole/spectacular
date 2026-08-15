<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Bambamboole\Spectacular\Contracts\HasApiFilters;
use Bambamboole\Spectacular\Contracts\HasApiIncludes;
use Bambamboole\Spectacular\Contracts\HasApiSorts;
use Bambamboole\Spectacular\QueryBuilder\Filters\FiltersBetween;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Workbench\App\Filters\RoleNameFilter;

/**
 * @property-read Collection<int, Role> $roles
 */
class User extends Model implements HasApiFilters, HasApiIncludes, HasApiSorts
{
    protected $guarded = [];

    /**
     * @return list<AllowedFilter>
     */
    public static function getApiFilters(): array
    {
        return [
            AllowedFilter::scope('created_after'),
            AllowedFilter::scope('created_before'),
            AllowedFilter::operator('created_at', FilterOperator::DYNAMIC),
            FiltersBetween::allowed('created_at'),
            AllowedFilter::exact('email'),
            AllowedFilter::custom('roles', new RoleNameFilter),
        ];
    }

    /**
     * @return list<AllowedSort|string>
     */
    public static function getApiSorts(): array
    {
        return [
            AllowedSort::field('created_at'),
            'updated_at',
        ];
    }

    /**
     * @return list<AllowedInclude|string>
     */
    public static function getApiIncludes(): array
    {
        return ['roles'];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Only users created at or after the given date.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeCreatedAfter(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '>=', $date);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeCreatedBefore(Builder $query, string $date): Builder
    {
        return $query->where('created_at', '<=', $date);
    }
}
