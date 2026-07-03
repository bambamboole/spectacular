<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;

final readonly class WebhookPayloadFactory
{
    public function make(WebhookEventDefinition $definition, object $event): WebhookPayload
    {
        $payloadMethod = $definition->attribute->payloadMethod;
        $eventClass = $event::class;
        $reflection = new ReflectionClass($event);

        if (! $reflection->hasMethod($payloadMethod)) {
            throw new RuntimeException("Webhook payload method [{$payloadMethod}] is missing on [{$eventClass}]");
        }

        $method = $reflection->getMethod($payloadMethod);

        if (! $method->isPublic()) {
            throw new RuntimeException("Webhook payload method [{$payloadMethod}] on [{$eventClass}] must be public");
        }

        if ($method->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeException("Webhook payload method [{$payloadMethod}] on [{$eventClass}] must have zero required parameters");
        }

        $data = $method->invoke($event);

        if (! is_array($data)) {
            throw new RuntimeException("Webhook payload method [{$payloadMethod}] on [{$eventClass}] must return an array");
        }

        $id = (string) Str::uuid();

        return new WebhookPayload(
            id: $id,
            event: $definition->name,
            body: [
                'id' => $id,
                'event' => $definition->name,
                'createdAt' => now()->toISOString(),
                'data' => $data,
            ],
        );
    }
}
