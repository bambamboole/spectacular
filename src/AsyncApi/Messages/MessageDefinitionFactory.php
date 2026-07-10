<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Messages;

use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\Webhooks\WebhookEventDefinition;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use ReflectionClass;
use Stringable;
use Throwable;

final readonly class MessageDefinitionFactory
{
    public function __construct(
        private PayloadSchemaFactory $payloads,
    ) {}

    /**
     * @param  ReflectionClass<object>  $event
     */
    public function fromBroadcastEvent(ReflectionClass $event, Message $attribute, bool $includeLaravelExtensions): ?AsyncMessageDefinition
    {
        if (! $event->implementsInterface(ShouldBroadcast::class)
            && ! $event->implementsInterface(ShouldBroadcastNow::class)) {
            return null;
        }

        $channels = $attribute->channels !== []
            ? $attribute->channels
            : $this->inferChannels($event);

        if ($channels === []) {
            return null;
        }

        $message = array_filter([
            'name' => $this->broadcastName($event),
            'title' => $attribute->title,
            'summary' => $attribute->summary,
            'description' => $attribute->description,
            'tags' => array_map(fn (string $tag): array => ['name' => $tag], $attribute->tags),
            'payload' => $this->payloads->forEvent($event->getName()),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        if ($includeLaravelExtensions) {
            $message['x-laravel-event'] = $event->getName();
            $message['x-laravel-broadcast-now'] = $event->implementsInterface(ShouldBroadcastNow::class);
        }

        if ($attribute->payload !== null) {
            $message['x-spectacular-payload'] = $attribute->payload;
        }

        return new AsyncMessageDefinition(
            key: $this->componentKey($event->getName()),
            name: $message['name'],
            channels: array_map(fn (string $channel): AsyncChannelDefinition => new AsyncChannelDefinition(
                key: $channel,
                address: $channel,
            ), $channels),
            message: $message,
        );
    }

    /**
     * @param  ReflectionClass<object>  $notification
     */
    public function fromBroadcastNotification(ReflectionClass $notification, BroadcastNotification $attribute, bool $includeLaravelExtensions): ?AsyncMessageDefinition
    {
        $channels = $attribute->channels !== []
            ? $attribute->channels
            : $this->inferNotificationChannels($attribute->notifiables, $notification);

        if ($channels === []) {
            return null;
        }

        $message = array_filter([
            'name' => $this->notificationBroadcastName($notification),
            'title' => $attribute->title,
            'summary' => $attribute->summary,
            'description' => $attribute->description,
            'tags' => array_map(fn (string $tag): array => ['name' => $tag], $attribute->tags),
            'payload' => $this->payloads->forNotification($notification->getName()),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        if ($includeLaravelExtensions) {
            $message['x-laravel-notification'] = $notification->getName();
            $message['x-laravel-event'] = BroadcastNotificationCreated::class;
            $message['x-laravel-broadcast-now'] = false;
        }

        if ($attribute->payload !== null) {
            $message['x-spectacular-payload'] = $attribute->payload;
        }

        return new AsyncMessageDefinition(
            key: $this->componentKey($notification->getName()),
            name: $message['name'],
            channels: array_map(fn (string $channel): AsyncChannelDefinition => new AsyncChannelDefinition(
                key: $channel,
                address: $channel,
            ), $channels),
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $webhooks
     */
    public function fromWebhook(WebhookEventDefinition $definition, array $webhooks = []): AsyncMessageDefinition
    {
        $channel = $webhooks['channel'] ?? [];
        $channel = is_array($channel) ? $channel : [];
        $channelKey = is_string($channel['key'] ?? null) ? $channel['key'] : 'webhooks';
        $channelAddress = is_string($channel['address'] ?? null) ? $channel['address'] : '{webhookUrl}';
        $data = $definition->attribute->payload !== null
            ? ['$ref' => $definition->attribute->payload]
            : $this->payloads->forMethod($definition->class, $definition->attribute->payloadMethod);

        $message = array_filter([
            'name' => $definition->name,
            'title' => $definition->title,
            'summary' => $definition->summary,
            'description' => $definition->description,
            'tags' => array_map(fn (string $tag): array => ['name' => $tag], $definition->tags),
            'headers' => $this->webhookHeaders($definition, $webhooks),
            'payload' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'format' => 'uuid',
                    ],
                    'event' => [
                        'type' => 'string',
                        'enum' => [$definition->name],
                    ],
                    'createdAt' => [
                        'type' => 'string',
                        'format' => 'date-time',
                    ],
                    'data' => $data,
                ],
                'required' => ['id', 'event', 'createdAt', 'data'],
            ],
            'x-spectacular-webhook-event' => $definition->name,
            'x-spectacular-source-class' => $definition->class,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return new AsyncMessageDefinition(
            key: $definition->name,
            name: $definition->name,
            channels: [
                new AsyncChannelDefinition(
                    key: $channelKey,
                    address: $channelAddress,
                    kind: 'webhook',
                ),
            ],
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $webhooks
     * @return array<string, mixed>
     */
    private function webhookHeaders(WebhookEventDefinition $definition, array $webhooks): array
    {
        $headers = $this->normalizeHeaders(is_array($webhooks['headers'] ?? null) ? $webhooks['headers'] : []);
        $headers = array_replace($headers, $this->normalizeHeaders($definition->attribute->headers));

        return array_filter([
            'type' => 'object',
            'properties' => $headers,
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  array<mixed>  $headers
     * @return array<string, mixed>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $schema) {
            if (is_int($name) && is_string($schema)) {
                $normalized[$schema] = ['type' => 'string'];

                continue;
            }

            if (is_string($name) && is_array($schema)) {
                $normalized[$name] = $schema;
            }
        }

        return $normalized;
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return list<string>
     */
    private function inferChannels(ReflectionClass $event): array
    {
        if (! $event->hasMethod('broadcastOn')) {
            return [];
        }

        $method = $event->getMethod('broadcastOn');

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return [];
        }

        try {
            return $this->normalizeChannels($method->invoke($event->newInstanceWithoutConstructor()));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<class-string>  $notifiables
     * @param  ReflectionClass<object>  $notification
     * @return list<string>
     */
    private function inferNotificationChannels(array $notifiables, ReflectionClass $notification): array
    {
        return collect($notifiables)
            ->flatMap(function (string $notifiable) use ($notification): array {
                $reflection = new ReflectionClass($notifiable);

                return $this->normalizeChannels(
                    $this->receivesBroadcastNotificationsOn($reflection, $notification) ?? $this->defaultNotifiableChannel($reflection),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  ReflectionClass<object>  $notifiable
     * @param  ReflectionClass<object>  $notification
     */
    private function receivesBroadcastNotificationsOn(ReflectionClass $notifiable, ReflectionClass $notification): mixed
    {
        if (! $notifiable->hasMethod('receivesBroadcastNotificationsOn')) {
            return null;
        }

        $method = $notifiable->getMethod('receivesBroadcastNotificationsOn');

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 1) {
            return null;
        }

        try {
            $notifiableInstance = $notifiable->newInstanceWithoutConstructor();

            if ($method->getNumberOfParameters() === 0) {
                return $method->invoke($notifiableInstance);
            }

            return $method->invoke($notifiableInstance, $notification->newInstanceWithoutConstructor());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  ReflectionClass<object>  $notifiable
     */
    private function defaultNotifiableChannel(ReflectionClass $notifiable): string
    {
        $class = $this->componentKey($notifiable->getName());
        $placeholder = lcfirst($notifiable->getShortName()).'Id';

        return "private-{$class}.{{$placeholder}}";
    }

    /**
     * @return list<string>
     */
    private function normalizeChannels(mixed $channels): array
    {
        if (is_string($channels) || $channels instanceof Stringable) {
            return [(string) $channels];
        }

        if (! is_iterable($channels)) {
            return [];
        }

        $normalized = [];

        foreach ($channels as $channel) {
            array_push($normalized, ...$this->normalizeChannels($channel));
        }

        return $normalized;
    }

    /**
     * @param  ReflectionClass<object>  $event
     */
    private function broadcastName(ReflectionClass $event): string
    {
        if (! $event->hasMethod('broadcastAs')) {
            return $event->getName();
        }

        $method = $event->getMethod('broadcastAs');

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return $event->getName();
        }

        try {
            $name = $method->invoke($event->newInstanceWithoutConstructor());

            return is_string($name) && $name !== '' ? $name : $event->getName();
        } catch (Throwable) {
            return $event->getName();
        }
    }

    /**
     * @param  ReflectionClass<object>  $notification
     */
    private function notificationBroadcastName(ReflectionClass $notification): string
    {
        if (! $notification->hasMethod('broadcastAs')) {
            return BroadcastNotificationCreated::class;
        }

        $method = $notification->getMethod('broadcastAs');

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return BroadcastNotificationCreated::class;
        }

        try {
            $name = $method->invoke($notification->newInstanceWithoutConstructor());

            return is_string($name) && $name !== '' ? $name : BroadcastNotificationCreated::class;
        } catch (Throwable) {
            return BroadcastNotificationCreated::class;
        }
    }

    private function componentKey(string $class): string
    {
        return str_replace('\\', '.', $class);
    }
}
