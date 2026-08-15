<?php
declare(strict_types=1);

namespace Workbench\App\Models;

use Bambamboole\Spectacular\Contracts\HasPublicFilters;
use Bambamboole\Spectacular\Contracts\HasPublicSorts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * @property-read Collection<int, Role> $roles
 */
class User extends Model implements HasPublicFilters, HasPublicSorts
{
    protected $guarded = [];

    /**
     * @return list<AllowedFilter>
     */
    public static function getFilters(): array
    {
        return [
            AllowedFilter::scope('created_after'),
            AllowedFilter::scope('created_before'),
            AllowedFilter::exact('email'),
        ];
    }

    /**
     * @return list<AllowedSort|string>
     */
    public static function getSorts(): array
    {
        return [
            AllowedSort::field('created_at'),
            'updated_at',
        ];
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
