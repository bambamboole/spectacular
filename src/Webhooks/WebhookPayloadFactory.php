<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use Illuminate\Support\Str;
use RuntimeException;

final readonly class WebhookPayloadFactory
{
    public function make(WebhookEventDefinition $definition, object $event): WebhookPayload
    {
        $payloadMethod = $definition->attribute->payloadMethod;
        $eventClass = $event::class;

        if (! is_callable([$event, $payloadMethod])) {
            throw new RuntimeException("Webhook payload method [{$payloadMethod}] is missing on [{$eventClass}]");
        }

        $data = $event->{$payloadMethod}();

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
