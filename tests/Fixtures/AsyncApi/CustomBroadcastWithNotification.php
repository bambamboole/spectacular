<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Illuminate\Notifications\Notification;

final class CustomBroadcastWithNotification extends Notification
{
    /**
     * @return array{invoiceId:int}
     */
    public function broadcastWith(): array
    {
        return [
            'invoiceId' => 123,
        ];
    }
}
