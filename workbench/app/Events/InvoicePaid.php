<?php
declare(strict_types=1);

namespace Workbench\App\Events;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

#[WebhookEvent(name: 'invoice.paid', title: 'Invoice Paid', summary: 'Sent when a workbench invoice is paid.', tags: ['billing'])]
final class InvoicePaid
{
    public function __construct(
        public int $invoiceId,
        public int $amount,
    ) {}

    /**
     * @return array{invoiceId:int, amount:int}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => $this->invoiceId,
            'amount' => $this->amount,
        ];
    }
}
