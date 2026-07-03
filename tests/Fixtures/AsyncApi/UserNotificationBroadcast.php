<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[Message(
    channels: ['private-users.{userId}'],
    title: 'User Notification',
    summary: 'User notification was created',
    description: 'Sent when a user receives a notification.',
    tags: ['notifications'],
)]
final class UserNotificationBroadcast implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notification.created';
    }

    /**
     * @return array{notificationId:int, team:string, urgent:bool, tags:list<string>, sentAt:CarbonImmutable, status:BroadcastStatus}
     */
    public function broadcastWith(): array
    {
        return [
            'notificationId' => 1,
            'team' => 'Acme',
            'urgent' => true,
            'tags' => ['billing'],
            'sentAt' => CarbonImmutable::parse('2026-07-03 12:00:00'),
            'status' => BroadcastStatus::Sent,
        ];
    }
}
