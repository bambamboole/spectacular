<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Carbon\CarbonImmutable;

#[WebhookEvent(
    name: 'invoice.refunded',
    title: 'Invoice Refunded',
    summary: 'Sent when an invoice is refunded.',
    description: 'Customers can subscribe to this webhook to react to refunded invoices.',
    tags: ['billing', 'refunds'],
)]
final class InvoiceRefundedWebhook
{
    public function __construct(
        public int $invoiceId = 123,
        public int $refundId = 456,
    ) {}

    /**
     * @return array{invoiceId:int, refundId:int, refundedAt:CarbonImmutable, status:BroadcastStatus}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => $this->invoiceId,
            'refundId' => $this->refundId,
            'refundedAt' => CarbonImmutable::parse('2026-07-03 13:00:00'),
            'status' => BroadcastStatus::Sent,
        ];
    }
}
