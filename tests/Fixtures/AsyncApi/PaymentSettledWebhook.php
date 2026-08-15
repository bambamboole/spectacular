<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests\Fixtures\AsyncApi;

use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;
use Brick\Math\BigDecimal;

#[WebhookEvent(
    name: 'payment.settled',
    title: 'Payment Settled',
    summary: 'Sent when a payment settles.',
)]
final class PaymentSettledWebhook
{
    public function __construct(
        public int $paymentId = 42,
    ) {}

    /**
     * @return array{paymentId:int, amount:BigDecimal}
     */
    public function webhookPayload(): array
    {
        return [
            'paymentId' => $this->paymentId,
            'amount' => BigDecimal::of('19.99'),
        ];
    }
}
