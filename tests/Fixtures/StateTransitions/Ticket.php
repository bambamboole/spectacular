<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;

/**
 * @property int $id
 * @property TicketState $status
 * @property string|null $resolution
 */
class Ticket extends Model
{
    use HasStates;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketState::class,
        ];
    }
}
