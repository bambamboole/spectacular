<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\StateTransitions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
final class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'resolution' => $this->resolution,
        ];
    }
}
