<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Messages;

use Bambamboole\LaravelWebhooks\WebhookEventDefinition;
use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Bambamboole\Spectacular\AsyncApi\Support\InvokesZeroArgMethods;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use ReflectionClass;
use Stringable;
use Throwable;

final readonly class MessageDefinitionFactory
{
    use InvokesZeroArgMethods;

    public function __construct(
        private PayloadSchemaFactory $payloads,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function referencedSchemas(): array
    {
        return $this->payloads->referencedSchemas();
    }

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

        $message = $this->message(
            $this->broadcastName($event),
            $attribute,
            $this->payloads->forEvent($event->getName()),
            $includeLaravelExtensions ? [
                'x-laravel-event' => $event->getName(),
                'x-laravel-broadcast-now' => $event->implementsInterface(ShouldBroadcastNow::class),
            ] : [],
        );

        return $this->definition($event->getName(), $channels, $message);
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

        $message = $this->message(
            $this->notificationBroadcastName($notification),
            $attribute,
            $this->payloads->forNotification($notification->getName()),
            $includeLaravelExtensions ? [
                'x-laravel-notification' => $notification->getName(),
                'x-laravel-event' => BroadcastNotificationCreated::class,
                'x-laravel-broadcast-now' => false,
            ] : [],
        );

        return $this->definition($notification->getName(), $channels, $message);
    }

    /**
     * @param  array<string, mixed>  $webhooks
     */
    public function fromWebhook(WebhookEventDefinition $definition, array $webhooks = []): AsyncMessageDefinition
    {
        $properties = [
            'id' => ['type' => 'string', 'format' => 'uuid'],
            'event' => ['type' => 'string', 'enum' => [$definition->name]],
            'createdAt' => ['type' => 'string', 'format' => 'date-time'],
            'data' => $this->payloads->forMethod($definition->class, 'webhookPayload'),
        ];

        if ($this->publicZeroArgMethod(new ReflectionClass($definition->class), 'webhookLinks') !== null) {
            $properties['links'] = $this->payloads->forMethod($definition->class, 'webhookLinks');
        }

        $message = array_filter([
            'name' => $definition->name,
            'title' => $definition->title,
            'summary' => $definition->summary,
            'description' => $definition->description,
            'tags' => array_map(fn (string $tag): array => ['name' => $tag], $definition->tags),
            'headers' => $this->webhookHeaders($webhooks),
            'payload' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => ['id', 'event', 'createdAt', 'data'],
            ],
            'x-spectacular-webhook-event' => $definition->name,
            'x-spectacular-source-class' => $definition->class,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        return new AsyncMessageDefinition(
            key: $definition->name,
            channels: [
                new AsyncChannelDefinition(
                    key: $this->stringSetting($webhooks['channel']['key'] ?? null, 'webhooks'),
                    address: $this->stringSetting($webhooks['channel']['address'] ?? null, '{webhookUrl}'),
                    kind: 'webhook',
                ),
            ],
            message: $message,
        );
    }

    private function stringSetting(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $laravelExtensions
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function message(string $name, Message $attribute, array $payload, array $laravelExtensions): array
    {
        $message = array_filter([
            'name' => $name,
            'title' => $attribute->title,
            'summary' => $attribute->summary,
            'description' => $attribute->description,
            'tags' => array_map(fn (string $tag): array => ['name' => $tag], $attribute->tags),
            'payload' => $payload,
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        $message = array_merge($message, $laravelExtensions);

        if ($attribute->payload !== null) {
            $message['x-spectacular-payload'] = $attribute->payload;
        }

        return $message;
    }

    /**
     * @param  list<string>  $channels
     * @param  array<string, mixed>  $message
     */
    private function definition(string $class, array $channels, array $message): AsyncMessageDefinition
    {
        return new AsyncMessageDefinition(
            key: $this->componentKey($class),
            channels: array_map(fn (string $channel): AsyncChannelDefinition => new AsyncChannelDefinition(
                key: $channel,
                address: $channel,
            ), $channels),
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $webhooks
     * @return array<string, mixed>
     */
    private function webhookHeaders(array $webhooks): array
    {
        $headers = array_filter(
            is_array($webhooks['headers'] ?? null) ? $webhooks['headers'] : [],
            fn (mixed $schema, mixed $name): bool => is_string($name) && is_array($schema),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_filter([
            'type' => 'object',
            'properties' => $headers,
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return list<string>
     */
    private function inferChannels(ReflectionClass $event): array
    {
        return $this->normalizeChannels($this->invokeZeroArgMethod($event, 'broadcastOn'));
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
        $name = $this->invokeZeroArgMethod($event, 'broadcastAs');

        return is_string($name) && $name !== '' ? $name : $event->getName();
    }

    /**
     * @param  ReflectionClass<object>  $notification
     */
    private function notificationBroadcastName(ReflectionClass $notification): string
    {
        $name = $this->invokeZeroArgMethod($notification, 'broadcastAs');

        return is_string($name) && $name !== '' ? $name : BroadcastNotificationCreated::class;
    }

    private function componentKey(string $class): string
    {
        return str_replace('\\', '.', $class);
    }
}
