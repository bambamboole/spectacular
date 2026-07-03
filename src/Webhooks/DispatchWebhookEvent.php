<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use RuntimeException;
use Spatie\WebhookServer\WebhookCall;

final readonly class DispatchWebhookEvent
{
    public function __construct(
        private WebhookEventRegistry $events,
        private WebhookPayloadFactory $payloads,
        private WebhookSubscriptionRepository $subscriptions,
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

        foreach ($this->subscriptions->forEvent($definition->name, $event) as $subscription) {
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
        foreach ($this->events->all() as $definition) {
            if ($definition->class === $event::class) {
                return $definition;
            }
        }

        return null;
    }

    private function supports(object $target, string $method): bool
    {
        return method_exists($target, $method);
    }
}
