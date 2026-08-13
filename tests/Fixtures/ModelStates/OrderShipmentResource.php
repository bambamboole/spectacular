<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\ModelStates;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Shipped $status
 */
class OrderShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status,
        ];
    }
}
