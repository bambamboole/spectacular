<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use RuntimeException;
use Spatie\WebhookServer\WebhookCall;

final class DispatchWebhookEvent
{
    /**
     * @var array<class-string, WebhookEventDefinition>|null
     */
    private ?array $definitionsByClass = null;

    public function __construct(
        private readonly WebhookEventRegistry $events,
        private readonly WebhookPayloadFactory $payloads,
        private readonly WebhookSubscriptionRepository $subscriptions,
    ) {}

    public function handle(object $event): void
    {
        $definition = $this->definitionFor($event);

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

    private function definitionFor(object $event): ?WebhookEventDefinition
    {
        return $this->definitionsByClass()[$event::class] ?? null;
    }

    /**
     * @return array<class-string, WebhookEventDefinition>
     */
    private function definitionsByClass(): array
    {
        if ($this->definitionsByClass !== null) {
            return $this->definitionsByClass;
        }

        $definitions = [];

        foreach ($this->events->all() as $definition) {
            $definitions[$definition->class] = $definition;
        }

        return $this->definitionsByClass = $definitions;
    }

    /**
     * @return iterable<mixed>
     */
    private function subscriptionsFor(string $eventName, object $event): iterable
    {
        return $this->subscriptions->forEvent($eventName, $event);
    }

    private function supports(object $target, string $method): bool
    {
        return method_exists($target, $method);
    }
}
