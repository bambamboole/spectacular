<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

#[Message(summary: 'Order updated')]
final class ImmediateBroadcast implements ShouldBroadcastNow
{
    public function __construct(
        public string $orderNumber,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('orders');
    }
}
