<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Carbon\CarbonImmutable;

#[WebhookEvent(
    name: 'invoice.paid',
    title: 'Invoice Paid',
    summary: 'Sent when an invoice is paid.',
    description: 'Customers can subscribe to this webhook to react to paid invoices.',
    tags: ['billing'],
)]
final class InvoicePaidWebhook
{
    public function __construct(
        public int $invoiceId = 123,
        public int $amount = 4999,
    ) {}

    /**
     * @return array{invoiceId:int, amount:int, paidAt:CarbonImmutable, status:BroadcastStatus}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => $this->invoiceId,
            'amount' => $this->amount,
            'paidAt' => CarbonImmutable::parse('2026-07-03 12:00:00'),
            'status' => BroadcastStatus::Sent,
        ];
    }
}
