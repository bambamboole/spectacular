<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[Message(channels: ['presence-teams.{teamId}'])]
final class PublicPropertiesBroadcast implements ShouldBroadcast
{
    /**
     * @param  array<int, string>  $labels
     */
    public function __construct(
        public int $teamId,
        public ?string $displayName,
        public array $labels,
        public BroadcastStatus $status,
        public CarbonImmutable $createdAt,
        public ExternalPayload $payload,
    ) {}

    public string $broadcastQueue = 'broadcasts';

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('teams.'.$this->teamId);
    }
}
