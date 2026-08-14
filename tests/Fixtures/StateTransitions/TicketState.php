<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Bambamboole\Spectacular\Attributes\StateEndpoint;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Ticket>
 */
#[StateEndpoint]
abstract class TicketState extends State
{
    #[\Override]
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Open::class)
            ->allowTransition(Open::class, Resolved::class, ResolveTicket::class)
            ->allowTransition(Resolved::class, Closed::class)
            ->allowTransition(Closed::class, Open::class);
    }
}
