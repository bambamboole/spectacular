<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\LaravelData;

use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * Registered as a global transformer when brick/math is installed: BigDecimal
 * properties serialize to their plain decimal string, preserving the scale the
 * value carries.
 */
final class BigDecimalTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        if (! $value instanceof BigDecimal) {
            throw new InvalidArgumentException(sprintf('%s can only transform %s instances.', self::class, BigDecimal::class));
        }

        return (string) $value;
    }
}
