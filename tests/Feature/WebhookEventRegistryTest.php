<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoiceRefundedWebhook;
use Bambamboole\Spectacular\Webhooks\WebhookEventRegistry;

it('discovers webhook event definitions sorted by event name', function (): void {
    config()->set('spectacular.asyncapi.webhooks.scan_paths', [
        dirname(__DIR__).'/Fixtures/AsyncApi',
    ]);

    $definitions = app(WebhookEventRegistry::class)->all();

    expect($definitions)->toHaveCount(2)
        ->and(array_map(fn ($definition): string => $definition->name, $definitions))->toBe([
            'invoice.paid',
            'invoice.refunded',
        ]);

    $paid = $definitions[0];
    $refunded = $definitions[1];

    expect($paid->class)->toBe(InvoicePaidWebhook::class)
        ->and($paid->title)->toBe('Invoice Paid')
        ->and($paid->summary)->toBe('Sent when an invoice is paid.')
        ->and($paid->description)->toBe('Customers can subscribe to this webhook to react to paid invoices.')
        ->and($paid->tags)->toBe(['billing'])
        ->and($paid->attribute->name)->toBe('invoice.paid');

    expect($refunded->class)->toBe(InvoiceRefundedWebhook::class)
        ->and($refunded->title)->toBe('Invoice Refunded')
        ->and($refunded->summary)->toBe('Sent when an invoice is refunded.')
        ->and($refunded->description)->toBe('Customers can subscribe to this webhook to react to refunded invoices.')
        ->and($refunded->tags)->toBe(['billing', 'refunds'])
        ->and($refunded->attribute->name)->toBe('invoice.refunded');
});

it('rejects duplicate webhook event names', function (): void {
    config()->set('spectacular.asyncapi.webhooks.scan_paths', [
        dirname(__DIR__).'/Fixtures/AsyncApi/DuplicateWebhooks',
    ]);

    expect(fn () => app(WebhookEventRegistry::class)->all())
        ->toThrow(LogicException::class, 'Duplicate webhook event name [invoice.paid]');
});

it('is registered as a singleton', function (): void {
    expect(app(WebhookEventRegistry::class))->toBe(app(WebhookEventRegistry::class));
});
