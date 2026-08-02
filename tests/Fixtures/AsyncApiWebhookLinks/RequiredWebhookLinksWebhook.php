<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApiWebhookLinks;

use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;

#[WebhookEvent(name: 'required.links')]
final class RequiredWebhookLinksWebhook
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
    public function webhookLinks(int $invoiceId): array
    {
        return ['self' => 'https://example.test/invoices/'.$invoiceId];
    }
}
