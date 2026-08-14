<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Spatie\ModelStates\Transition;

final class ResolveTicket extends Transition
{
    public function __construct(
        private readonly Ticket $ticket,
        private readonly ResolveTicketData $data,
    ) {}

    public function handle(): Ticket
    {
        $this->ticket->status = new Resolved($this->ticket);
        $this->ticket->resolution = $this->data->reason;
        $this->ticket->save();

        return $this->ticket;
    }
}
