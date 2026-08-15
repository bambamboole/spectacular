<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\Filters;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<BigDecimal, string>
 */
final class AsMoney implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        return $value === null ? null : BigDecimal::of((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
