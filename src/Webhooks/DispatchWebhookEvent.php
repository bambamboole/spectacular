<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use RuntimeException;
use Spatie\WebhookServer\WebhookCall;

final class DispatchWebhookEvent
{
    public function __construct(
        private readonly WebhookEventRegistry $events,
        private readonly WebhookPayloadFactory $payloads,
        private readonly WebhookSubscriptionRepository $subscriptions,
    ) {}

    public function handle(object $event): void
    {
        $definition = $this->events->forClass($event::class);

        if ($definition === null) {
            return;
        }

        if (! class_exists(WebhookCall::class)) {
            throw new RuntimeException(
                'Dispatching webhook events requires spatie/laravel-webhook-server. Install it to use DispatchWebhookEvent.',
            );
        }

        $payload = $this->payloads->make($definition, $event);

        foreach ($this->subscriptionsFor($definition->name, $event) as $subscription) {
            if (! $subscription instanceof WebhookSubscription) {
                throw new RuntimeException(
                    'Webhook subscription repositories must yield [Bambamboole\Spectacular\Webhooks\WebhookSubscription] instances.',
                );
            }

            $call = WebhookCall::create()
                ->url($subscription->url)
                ->payload($payload->body)
                ->withHeaders($subscription->headers)
                ->meta([
                    'event' => $definition->name,
                    'subscription_id' => $subscription->id,
                    'payload_id' => $payload->id,
                ]);

            if ($subscription->secret !== null) {
                $call->useSecret($subscription->secret);
            } elseif ($this->supports($call, 'doNotSign')) {
                $call->doNotSign();
            }

            if (config('spectacular.asyncapi.webhooks.dispatcher.use_timestamp', true)
                && $this->supports($call, 'useTimestamp')) {
                $call->useTimestamp();
            }

            $call->dispatch();
        }
    }

    /**
     * App repositories may yield anything at runtime despite the interface
     * generics, so widen to mixed to keep the instanceof guard meaningful.
     *
     * @return iterable<mixed>
     */
    private function subscriptionsFor(string $eventName, object $event): iterable
    {
        return $this->subscriptions->forEvent($eventName, $event);
    }

    /**
     * Widening to object keeps the runtime guard for hosts running older
     * spatie/laravel-webhook-server releases without these methods.
     */
    private function supports(object $target, string $method): bool
    {
        return method_exists($target, $method);
    }
}
