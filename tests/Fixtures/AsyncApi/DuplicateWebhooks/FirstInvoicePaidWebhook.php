<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\DuplicateWebhooks;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

#[WebhookEvent(name: 'invoice.paid', title: 'First Invoice Paid')]
final class FirstInvoicePaidWebhook
{
    /**
     * @return array{invoiceId:int}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => 123,
        ];
    }
}
