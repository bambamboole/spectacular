<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\WebhookRegistry\DuplicateWebhooks;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

#[WebhookEvent(name: 'invoice.paid', title: 'Second Invoice Paid')]
final class SecondInvoicePaidWebhook
{
    /**
     * @return array{invoiceId:int}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => 456,
        ];
    }
}
