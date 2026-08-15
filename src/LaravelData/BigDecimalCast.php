<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\LaravelData;

use Brick\Math\BigDecimal;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\Uncastable;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Registered as a global cast when brick/math is installed: payloads may
 * provide a BigDecimal-typed property as string, int, or float. No scale is
 * applied — canonical scale belongs at the persistence boundary.
 */
final class BigDecimalCast implements Cast
{
    /**
     * @template TData of \Spatie\LaravelData\Contracts\BaseData
     *
     * @param  array<string, mixed>  $properties
     * @param  CreationContext<TData>  $context
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): BigDecimal|Uncastable
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if (is_float($value)) {
            $value = (string) $value;
        }

        if (is_int($value) || is_string($value)) {
            return BigDecimal::of($value);
        }

        return Uncastable::create();
    }
}
