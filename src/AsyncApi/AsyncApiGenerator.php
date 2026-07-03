<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi;

use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Bambamboole\Spectacular\AsyncApi\Support\ClassDiscoverer;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use ReflectionAttribute;
use ReflectionClass;
use Stringable;
use Throwable;

final readonly class AsyncApiGenerator
{
    public function __construct(
        private ClassDiscoverer $classes,
        private PayloadSchemaFactory $payloads,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public function generate(?array $settings = null): array
    {
        $settings = array_replace_recursive(config('spectacular.asyncapi', []), $settings ?? []);
        $events = $this->broadcastEvents($settings['scan_paths'] ?? []);

        $channels = [];
        $operations = [];
        $messages = [];
        $includeLaravelExtensions = (bool) ($settings['laravel_extensions'] ?? true);

        foreach ($events as $event) {
            $attribute = $event->getAttributes(Message::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
            $eventChannels = $attribute->channels !== []
                ? $attribute->channels
                : $this->inferChannels($event);

            if ($eventChannels === []) {
                continue;
            }

            $messageKey = $this->componentKey($event->getName());
            $messageRef = '#/components/messages/'.$this->jsonPointerSegment($messageKey);

            foreach ($eventChannels as $channel) {
                $channels[$channel] ??= [
                    'address' => $channel,
                    'messages' => [],
                ];

                if ($includeLaravelExtensions) {
                    $channels[$channel]['x-laravel-channel-type'] = $this->channelType($channel);
                }

                $channels[$channel]['messages'][$messageKey] = [
                    '$ref' => $messageRef,
                ];
            }

            $operations[$messageKey.'.send'] = [
                'action' => 'send',
                'channel' => [
                    '$ref' => '#/channels/'.$this->jsonPointerSegment($eventChannels[0]),
                ],
                'messages' => [
                    ['$ref' => '#/channels/'.$this->jsonPointerSegment($eventChannels[0]).'/messages/'.$this->jsonPointerSegment($messageKey)],
                ],
            ];

            $messages[$messageKey] = array_filter([
                'name' => $this->broadcastName($event),
                'title' => $attribute->title,
                'summary' => $attribute->summary,
                'description' => $attribute->description,
                'tags' => array_map(fn (string $tag): array => ['name' => $tag], $attribute->tags),
                'payload' => $this->payloads->forEvent($event->getName()),
            ], fn (mixed $value): bool => $value !== null && $value !== []);

            if ($includeLaravelExtensions) {
                $messages[$messageKey]['x-laravel-event'] = $event->getName();
                $messages[$messageKey]['x-laravel-broadcast-now'] = $event->implementsInterface(ShouldBroadcastNow::class);
            }

            if ($attribute->payload !== null) {
                $messages[$messageKey]['x-spectacular-payload'] = $attribute->payload;
            }
        }

        ksort($channels);
        ksort($operations);
        ksort($messages);

        return [
            'asyncapi' => $settings['version'] ?? '3.0.0',
            'info' => $settings['info'] ?? ['title' => config('app.name').' AsyncAPI', 'version' => '0.0.1'],
            'defaultContentType' => $settings['default_content_type'] ?? 'application/json',
            'channels' => $channels,
            'operations' => $operations,
            'components' => [
                'messages' => $messages,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $paths
     * @return list<ReflectionClass<object>>
     */
    private function broadcastEvents(array $paths): array
    {
        return collect($this->classes->classesIn($paths))
            ->map(fn (string $class): ReflectionClass => new ReflectionClass($class))
            ->filter(fn (ReflectionClass $event): bool => $event->isInstantiable())
            ->filter(fn (ReflectionClass $event): bool => $event->implementsInterface(ShouldBroadcast::class)
                || $event->implementsInterface(ShouldBroadcastNow::class))
            ->filter(function (ReflectionClass $event): bool {
                return $event->getAttributes(Message::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
            })
            ->sortBy(fn (ReflectionClass $event): string => $event->getName())
            ->values()
            ->all();
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

    private function componentKey(string $class): string
    {
        return str_replace('\\', '.', $class);
    }

    private function channelType(string $channel): string
    {
        return match (true) {
            str_starts_with($channel, 'private-encrypted-') => 'private-encrypted',
            str_starts_with($channel, 'private-') => 'private',
            str_starts_with($channel, 'presence-') => 'presence',
            default => 'public',
        };
    }

    private function jsonPointerSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
