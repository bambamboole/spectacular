<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

#[BroadcastNotification(
    notifiables: [UserNotifiable::class],
    title: 'Invoice Paid Notification',
    summary: 'Sent to users when an invoice is paid.',
    tags: ['billing'],
)]
final class InvoicePaidBroadcastNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * @return BroadcastMessage&object{data: array{invoiceId:int, amount:int, paidAt:CarbonImmutable}}
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'invoiceId' => 123,
            'amount' => 4999,
            'paidAt' => CarbonImmutable::parse('2026-07-03 12:00:00'),
        ]);
    }

    public function broadcastType(): string
    {
        return 'invoice.paid';
    }
}
