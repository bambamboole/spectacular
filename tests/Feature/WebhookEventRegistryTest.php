<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoiceRefundedWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\WebhookRegistry\NestedRoot\Nested\InvoiceVoidedWebhook;
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
        dirname(__DIR__).'/Fixtures/WebhookRegistry/DuplicateWebhooks',
    ]);

    expect(fn () => app(WebhookEventRegistry::class)->all())
        ->toThrow(LogicException::class, 'Duplicate webhook event name [invoice.paid]');
});

it('discovers nested webhook event definitions recursively', function (): void {
    config()->set('spectacular.asyncapi.webhooks.scan_paths', [
        dirname(__DIR__).'/Fixtures/WebhookRegistry/NestedRoot',
    ]);

    $definitions = app(WebhookEventRegistry::class)->all();

    expect($definitions)->toHaveCount(1)
        ->and($definitions[0]->name)->toBe('invoice.voided')
        ->and($definitions[0]->class)->toBe(InvoiceVoidedWebhook::class);
});

it('inherits base asyncapi scan paths when webhook scan paths are null', function (): void {
    config()->set('spectacular.asyncapi.scan_paths', [
        dirname(__DIR__).'/Fixtures/WebhookRegistry/NestedRoot',
    ]);
    config()->set('spectacular.asyncapi.webhooks.scan_paths', null);

    $definitions = app(WebhookEventRegistry::class)->all();

    expect($definitions)->toHaveCount(1)
        ->and($definitions[0]->name)->toBe('invoice.voided');
});

it('caches default webhook definitions by class for singleton lookups', function (): void {
    config()->set('spectacular.asyncapi.webhooks.scan_paths', [
        dirname(__DIR__).'/Fixtures/AsyncApi',
    ]);

    $registry = app(WebhookEventRegistry::class);

    expect($registry->forClass(InvoicePaidWebhook::class)?->name)->toBe('invoice.paid')
        ->and($registry->forClass(stdClass::class))->toBeNull();

    config()->set('spectacular.asyncapi.webhooks.scan_paths', []);

    expect(app(WebhookEventRegistry::class)->forClass(InvoicePaidWebhook::class)?->name)->toBe('invoice.paid')
        ->and(app(WebhookEventRegistry::class)->all())->toBe([]);
});

it('returns no events when webhook scan paths are explicitly empty', function (): void {
    config()->set('spectacular.asyncapi.scan_paths', [
        dirname(__DIR__).'/Fixtures/WebhookRegistry/NestedRoot',
    ]);
    config()->set('spectacular.asyncapi.webhooks.scan_paths', []);

    expect(app(WebhookEventRegistry::class)->all())->toBe([]);
});

it('is registered as a singleton', function (): void {
    expect(app(WebhookEventRegistry::class))->toBe(app(WebhookEventRegistry::class));
});
