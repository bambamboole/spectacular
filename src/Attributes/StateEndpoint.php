<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Attributes;

use Attribute;

/**
 * Marks a spatie/laravel-model-states base state class as exposed through a
 * templated transition route, so the generated document fans that route out
 * into one operation per reachable target state.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class StateEndpoint
{
    public function __construct(
        /**
         * The route as it appears in the generated document (the configured
         * API prefix stripped, no leading slash) with `{state}` as the
         * target-state placeholder.
         */
        public string $path,
        /**
         * Noun used in operation summaries and descriptions. Defaults to the
         * state class basename without its `State` suffix, lowercased.
         */
        public ?string $label = null,
        public string $method = 'patch',
    ) {}
}
