<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[Message(
    channels: ['catalog'],
    summary: 'A product became visible to buyers.',
    key: 'product.published',
)]
final class KeyedBroadcast implements ShouldBroadcast
{
    public function __construct(
        public string $productId,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('catalog');
    }

    public function broadcastAs(): string
    {
        return 'catalog.product.published';
    }
}
