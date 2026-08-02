<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApiWebhookLinks;

use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;

#[WebhookEvent(name: 'protected.links')]
final class ProtectedWebhookLinksWebhook
{
    /**
     * @return array{invoiceId:int}
     */
    public function webhookPayload(): array
    {
        return ['invoiceId' => 123];
    }

    /**
     * @return array{self:string}
     */
    protected function webhookLinks(): array
    {
        return ['self' => 'https://example.test/invoices/123'];
    }
}
