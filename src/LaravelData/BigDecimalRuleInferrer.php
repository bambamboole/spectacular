<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\LaravelData;

use Brick\Math\BigDecimal;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\RuleInferrers\RuleInferrer;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Validation\PropertyRules;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * A BigDecimal-typed property gets no rule from laravel-data's built-in
 * inferrers; `numeric` makes the payload accept int, float, and numeric
 * string — exactly what BigDecimalCast can hydrate.
 */
final class BigDecimalRuleInferrer implements RuleInferrer
{
    public function handle(DataProperty $property, PropertyRules $rules, ValidationContext $context): PropertyRules
    {
        if ($property->type->acceptsType(BigDecimal::class) && ! $rules->hasType(Numeric::class)) {
            $rules->add(new Numeric);
        }

        return $rules;
    }
}
