<?php
declare(strict_types=1);

use Bambamboole\Spectacular\ModelStates\TransitionModelState;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\Resolved;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\ResolveTicketData;
use Bambamboole\Spectacular\Tests\Fixtures\StateTransitions\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('tickets', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('status');
        $table->string('resolution')->nullable();
        $table->timestamps();
    });

    RouteFacade::patch('api/tickets/{ticket}/transition-to/{state}', TicketTransitionsController::class)
        ->middleware(SubstituteBindings::class);
});

it('transitions without a body when the transition takes no payload', function (): void {
    $ticket = Ticket::create();
    $ticket->status->transitionTo(Resolved::class, ResolveTicketData::from(['reason' => 'setup']));

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/closed")
        ->assertOk()
        ->assertJsonPath('status', 'closed');

    expect($ticket->refresh()->status->getValue())->toBe('closed');
});

it('validates and hands the body to the transition constructor', function (): void {
    $ticket = Ticket::create();

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/resolved", ['reason' => 'Restarted the worker'])
        ->assertOk()
        ->assertJsonPath('status', 'resolved')
        ->assertJsonPath('resolution', 'Restarted the worker');

    expect($ticket->refresh()->resolution)->toBe('Restarted the worker');
});

it('responds 409 when the current state does not allow the transition', function (): void {
    $ticket = Ticket::create();

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/closed")
        ->assertStatus(409)
        ->assertJsonPath('current_state', 'open')
        ->assertJsonPath('requested_state', 'closed')
        ->assertJsonPath('allowed_states', ['resolved']);

    expect($ticket->refresh()->status->getValue())->toBe('open');
});

it('rejects an invalid body against the transition data class', function (): void {
    $ticket = Ticket::create();

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/resolved")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('rejects payload keys the transition data class does not declare', function (): void {
    $ticket = Ticket::create();

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/resolved", ['reason' => 'Restarted', 'typo' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['typo']);
});

it('rejects a body on a transition that accepts none', function (): void {
    $ticket = Ticket::create();
    $ticket->status->transitionTo(Resolved::class, ResolveTicketData::from(['reason' => 'setup']));

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/closed", ['reason' => 'unexpected'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payload']);
});

it('responds 404 for an unknown target state', function (): void {
    $ticket = Ticket::create();

    $this->patchJson("api/tickets/{$ticket->id}/transition-to/bogus")->assertNotFound();
});

final class TicketTransitionsController
{
    public function __invoke(Ticket $ticket, string $state, Request $request): Model
    {
        return new TransitionModelState()->handle($ticket, 'status', $state, $request->json()->all());
    }
}
