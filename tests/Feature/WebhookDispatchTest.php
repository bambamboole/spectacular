<?php
declare(strict_types=1);

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Bambamboole\Spectacular\SpectacularServiceProvider;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\BroadcastStatus;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Webhooks\WebhookEventDefinition;
use Bambamboole\Spectacular\Webhooks\WebhookPayloadFactory;
use Bambamboole\Spectacular\Webhooks\WebhookSubscription;
use Bambamboole\Spectacular\Webhooks\WebhookSubscriptionRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('builds a stable envelope for an invoice paid webhook', function (): void {
    Carbon::setTestNow('2026-07-03 12:34:56');

    $definition = invoicePaidWebhookDefinition();

    $payload = app(WebhookPayloadFactory::class)->make(
        $definition,
        new InvoicePaidWebhook(invoiceId: 987, amount: 6500),
    );

    expect(Str::isUuid($payload->id))->toBeTrue()
        ->and($payload->event)->toBe('invoice.paid')
        ->and($payload->body)->toEqual([
            'id' => $payload->id,
            'event' => 'invoice.paid',
            'createdAt' => '2026-07-03T12:34:56.000000Z',
            'data' => [
                'invoiceId' => 987,
                'amount' => 6500,
                'paidAt' => CarbonImmutable::parse('2026-07-03 12:00:00'),
                'status' => BroadcastStatus::Sent,
            ],
        ]);
});

it('uses a safe empty subscription repository by default', function (): void {
    $repository = app(WebhookSubscriptionRepository::class);

    expect($repository->forEvent('invoice.paid', new InvoicePaidWebhook))->toBeIterable()
        ->and(iterator_to_array($repository->forEvent('invoice.paid', new InvoicePaidWebhook)))->toBe([]);
});

it('preserves an existing subscription repository binding', function (): void {
    app()->bind(WebhookSubscriptionRepository::class, CustomWebhookSubscriptionRepository::class);

    (new SpectacularServiceProvider(app()))->register();

    $subscriptions = iterator_to_array(app(WebhookSubscriptionRepository::class)->forEvent('invoice.paid', new InvoicePaidWebhook));

    expect($subscriptions)->toHaveCount(1)
        ->and($subscriptions[0]->url)->toBe('https://example.com/webhooks');
});

it('throws a useful runtime exception when the payload method is missing', function (): void {
    $definition = new WebhookEventDefinition(
        name: 'invoice.missing',
        class: MissingPayloadMethodWebhook::class,
        title: null,
        summary: null,
        description: null,
        tags: [],
        attribute: new WebhookEvent(name: 'invoice.missing', payloadMethod: 'missingPayload'),
    );

    expect(fn () => app(WebhookPayloadFactory::class)->make($definition, new MissingPayloadMethodWebhook))
        ->toThrow(RuntimeException::class, 'Webhook payload method [missingPayload] is missing on [MissingPayloadMethodWebhook]');
});

it('throws a useful runtime exception when the payload method is handled by magic call', function (): void {
    $definition = new WebhookEventDefinition(
        name: 'invoice.magic',
        class: MagicPayloadMethodWebhook::class,
        title: null,
        summary: null,
        description: null,
        tags: [],
        attribute: new WebhookEvent(name: 'invoice.magic', payloadMethod: 'webhookPayload'),
    );

    expect(fn () => app(WebhookPayloadFactory::class)->make($definition, new MagicPayloadMethodWebhook))
        ->toThrow(RuntimeException::class, 'Webhook payload method [webhookPayload] is missing on [MagicPayloadMethodWebhook]');
});

it('throws a useful runtime exception when the payload method is not public', function (): void {
    $definition = new WebhookEventDefinition(
        name: 'invoice.private',
        class: PrivatePayloadMethodWebhook::class,
        title: null,
        summary: null,
        description: null,
        tags: [],
        attribute: new WebhookEvent(name: 'invoice.private', payloadMethod: 'webhookPayload'),
    );

    expect(fn () => app(WebhookPayloadFactory::class)->make($definition, new PrivatePayloadMethodWebhook))
        ->toThrow(RuntimeException::class, 'Webhook payload method [webhookPayload] on [PrivatePayloadMethodWebhook] must be public');
});

it('throws a useful runtime exception when the payload method requires parameters', function (): void {
    $definition = new WebhookEventDefinition(
        name: 'invoice.parameters',
        class: RequiredParameterPayloadWebhook::class,
        title: null,
        summary: null,
        description: null,
        tags: [],
        attribute: new WebhookEvent(name: 'invoice.parameters', payloadMethod: 'webhookPayload'),
    );

    expect(fn () => app(WebhookPayloadFactory::class)->make($definition, new RequiredParameterPayloadWebhook))
        ->toThrow(RuntimeException::class, 'Webhook payload method [webhookPayload] on [RequiredParameterPayloadWebhook] must have zero required parameters');
});

it('throws a useful runtime exception when the payload method does not return an array', function (): void {
    $definition = new WebhookEventDefinition(
        name: 'invoice.invalid',
        class: NonArrayPayloadWebhook::class,
        title: null,
        summary: null,
        description: null,
        tags: [],
        attribute: new WebhookEvent(name: 'invoice.invalid', payloadMethod: 'webhookPayload'),
    );

    expect(fn () => app(WebhookPayloadFactory::class)->make($definition, new NonArrayPayloadWebhook))
        ->toThrow(RuntimeException::class, 'Webhook payload method [webhookPayload] on [NonArrayPayloadWebhook] must return an array');
});

function invoicePaidWebhookDefinition(): WebhookEventDefinition
{
    return new WebhookEventDefinition(
        name: 'invoice.paid',
        class: InvoicePaidWebhook::class,
        title: 'Invoice Paid',
        summary: 'Sent when an invoice is paid.',
        description: 'Customers can subscribe to this webhook to react to paid invoices.',
        tags: ['billing'],
        attribute: new WebhookEvent(name: 'invoice.paid'),
    );
}

final class MissingPayloadMethodWebhook {}

final class MagicPayloadMethodWebhook
{
    /**
     * @param  array<int, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function __call(string $method, array $arguments): array
    {
        return ['magic' => $method];
    }
}

final class PrivatePayloadMethodWebhook
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->webhookPayload();
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(): array
    {
        return [];
    }
}

final class RequiredParameterPayloadWebhook
{
    /**
     * @return array<string, mixed>
     */
    public function webhookPayload(string $scope): array
    {
        return ['scope' => $scope];
    }
}

final class NonArrayPayloadWebhook
{
    public function webhookPayload(): string
    {
        return 'invalid';
    }
}

final class CustomWebhookSubscriptionRepository implements WebhookSubscriptionRepository
{
    public function forEvent(string $eventName, object $event): iterable
    {
        return [
            new WebhookSubscription(url: 'https://example.com/webhooks'),
        ];
    }
}
