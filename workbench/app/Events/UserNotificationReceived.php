<?php
declare(strict_types=1);

namespace Workbench\App\Events;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[Message(
    channels: ['private-users.{userId}'],
    summary: 'User notification received',
    description: 'Sent when a user receives a notification in the workbench app.',
    tags: ['notifications'],
)]
final class UserNotificationReceived implements ShouldBroadcast
{
    public function __construct(
        public int $userId,
        public string $team,
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
        return 'user.notification.received';
    }

    /**
     * @return array{userId:int, team:string, unreadCount:int}
     */
    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'team' => $this->team,
            'unreadCount' => 1,
        ];
    }
}
