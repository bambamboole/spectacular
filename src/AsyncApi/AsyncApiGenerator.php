<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi;

use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Bambamboole\Spectacular\AsyncApi\Messages\AsyncMessageDefinition;
use Bambamboole\Spectacular\AsyncApi\Messages\MessageDefinitionFactory;
use Bambamboole\Spectacular\Support\ClassDiscoverer;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;

final readonly class AsyncApiGenerator
{
    public function __construct(
        private ClassDiscoverer $classes,
        private MessageDefinitionFactory $messages,
        private ?WebhookEventRegistry $webhooks = null,
    ) {}

    /** @return array<string, mixed> */
    public function generate(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = config('spectacular.asyncapi', []);
        $includeLaravelExtensions = (bool) ($settings['laravel_extensions'] ?? true);
        $definitions = $this->messageDefinitions($settings, $includeLaravelExtensions);

        $channels = [];
        $channelDefinitions = [];
        $operations = [];
        $messages = [];

        foreach ($definitions as $definition) {
            $operationKey = $definition->key.'.send';

            if (isset($messages[$definition->key]) || isset($operations[$operationKey])) {
                throw new LogicException("Duplicate AsyncAPI message key [{$definition->key}] and operation key [{$operationKey}]");
            }

            $messageRef = '#/components/messages/'.$this->jsonPointerSegment($definition->key);

            foreach ($definition->channels as $channel) {
                $existingChannel = $channelDefinitions[$channel->key] ?? null;

                if ($existingChannel !== null
                    && ($existingChannel->address !== $channel->address || $existingChannel->kind !== $channel->kind)) {
                    throw new LogicException(
                        "Conflicting AsyncAPI channel key [{$channel->key}]: existing address [{$existingChannel->address}] and kind [{$existingChannel->kind}], incoming address [{$channel->address}] and kind [{$channel->kind}]",
                    );
                }

                $channelDefinitions[$channel->key] ??= $channel;
                $channels[$channel->key] ??= [
                    'address' => $channel->address,
                    'messages' => [],
                ];

                if ($includeLaravelExtensions && $channel->kind === 'laravel') {
                    $channels[$channel->key]['x-laravel-channel-type'] = $this->channelType($channel->address);
                }

                if ($channel->kind === 'webhook') {
                    $channels[$channel->key]['x-spectacular-channel-kind'] = 'webhook';
                }

                $channels[$channel->key]['messages'][$definition->key] = [
                    '$ref' => $messageRef,
                ];
            }

            $primaryChannel = $definition->channels[0];
            $operations[$operationKey] = [
                'action' => 'send',
                'channel' => [
                    '$ref' => '#/channels/'.$this->jsonPointerSegment($primaryChannel->key),
                ],
                'messages' => [
                    ['$ref' => '#/channels/'.$this->jsonPointerSegment($primaryChannel->key).'/messages/'.$this->jsonPointerSegment($definition->key)],
                ],
            ];

            $messages[$definition->key] = $definition->message;
        }

        foreach ($channels as $channelKey => $channel) {
            ksort($channel['messages']);

            $channels[$channelKey]['messages'] = $channel['messages'];
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
     * @param  array<string, mixed>  $settings
     * @return list<AsyncMessageDefinition>
     */
    private function messageDefinitions(array $settings, bool $includeLaravelExtensions): array
    {
        $messageDefinitions = collect($this->messageAttributedClasses($settings['scan_paths'] ?? []))
            ->map(function (ReflectionClass $class) use ($includeLaravelExtensions): ?AsyncMessageDefinition {
                $attribute = $class->getAttributes(Message::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();

                if ($attribute instanceof BroadcastNotification) {
                    return $this->messages->fromBroadcastNotification($class, $attribute, $includeLaravelExtensions);
                }

                return $this->messages->fromBroadcastEvent($class, $attribute, $includeLaravelExtensions);
            })
            ->filter()
            ->values()
            ->all();

        $webhookSettings = is_array($settings['webhooks'] ?? null) ? $settings['webhooks'] : [];

        foreach ($this->webhooks?->all() ?? [] as $webhook) {
            $messageDefinitions[] = $this->messages->fromWebhook($webhook, $webhookSettings);
        }

        return $messageDefinitions;
    }

    /**
     * @param  array<int, string>  $paths
     * @return list<ReflectionClass<object>>
     */
    private function messageAttributedClasses(array $paths): array
    {
        return collect($this->classes->classesIn($paths))
            ->map(fn (string $class): ReflectionClass => new ReflectionClass($class))
            ->filter(fn (ReflectionClass $class): bool => $class->isInstantiable())
            ->filter(function (ReflectionClass $class): bool {
                return $class->getAttributes(Message::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
            })
            ->sortBy(fn (ReflectionClass $class): string => $class->getName())
            ->values()
            ->all();
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
