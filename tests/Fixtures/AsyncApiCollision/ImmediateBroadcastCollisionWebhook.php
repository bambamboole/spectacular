<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApiCollision;

use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;

#[WebhookEvent(name: 'Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.ImmediateBroadcast')]
final class ImmediateBroadcastCollisionWebhook
{
    /**
     * @return array{invoiceId:int}
     */
    public function webhookPayload(): array
    {
        return ['invoiceId' => 123];
    }
}
