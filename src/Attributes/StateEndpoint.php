<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Marks a spatie/laravel-model-states base state class as exposed through a
 * templated transition route. Any documented route that carries a `{state}`
 * parameter and binds a model casting a field to the annotated class is
 * fanned out into one operation per reachable target state.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StateEndpoint
{
    public function __construct(
        /**
         * Noun used in operation summaries and descriptions. Defaults to the
         * state class basename without its `State` suffix, lowercased.
         */
        public ?string $label = null,
    ) {}
}
