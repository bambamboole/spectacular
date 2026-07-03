<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\WebhookRegistry\NestedRoot\Nested;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

#[WebhookEvent(name: 'invoice.voided', title: 'Invoice Voided')]
final class InvoiceVoidedWebhook
{
    /**
     * @return array{invoiceId:int}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => 789,
        ];
    }
}
