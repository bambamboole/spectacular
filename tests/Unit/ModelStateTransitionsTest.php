<?php
declare(strict_types=1);

use Bambamboole\Spectacular\ModelStates\ModelStateTransitions;
use Bambamboole\Spectacular\ModelStates\StateTransition;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\BrokenModel;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\Resolved;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\ResolveTicket;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\ResolveTicketData;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\Ticket;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\TicketState;

it('introspects the state machine of a model field', function (): void {
    $transitions = ModelStateTransitions::for(Ticket::class);

    expect($transitions->baseStateClass())->toBe(TicketState::class)
        ->and($transitions->stateMapping()->keys()->sort()->values()->all())->toBe(['closed', 'open', 'resolved'])
        ->and($transitions->allowedFrom('open'))->toBe(['resolved'])
        ->and($transitions->allowedFrom('resolved'))->toBe(['closed'])
        ->and(array_map(fn (StateTransition $transition): string => $transition->from, $transitions->into('open')))->toBe(['closed']);
});

it('detects the payload data class from the transition constructor', function (): void {
    $transitions = ModelStateTransitions::for(Ticket::class);

    $resolve = $transitions->find('open', 'resolved');
    $close = $transitions->find('resolved', 'closed');

    expect($resolve?->transitionClass)->toBe(ResolveTicket::class)
        ->and($resolve?->dataClass)->toBe(ResolveTicketData::class)
        ->and($close?->transitionClass)->toBeNull()
        ->and($close?->dataClass)->toBeNull()
        ->and($transitions->find('open', 'closed'))->toBeNull();
});

it('resolves state classes only for known names', function (): void {
    $transitions = ModelStateTransitions::for(Ticket::class);

    expect($transitions->resolveStateClass('resolved'))->toBe(Resolved::class)
        ->and($transitions->resolveStateClass('bogus'))->toBeNull();
});

it('rejects transition constructors with unsupported required parameters', function (): void {
    ModelStateTransitions::for(BrokenModel::class);
})->throws(LogicException::class, 'required constructor parameter [note]');

it('rejects fields that are not model states', function (): void {
    ModelStateTransitions::for(Ticket::class, 'resolution');
})->throws(InvalidArgumentException::class);
