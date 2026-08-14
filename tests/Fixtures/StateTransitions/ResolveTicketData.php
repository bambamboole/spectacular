<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Bambamboole\Spectacular\Attributes\SpecProperty;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

final class ResolveTicketData extends Data
{
    public function __construct(
        #[SpecProperty('How the ticket was resolved.')]
        #[Max(500)]
        public string $reason,
    ) {}
}
