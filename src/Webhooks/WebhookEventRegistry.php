<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Bambamboole\Spectacular\AsyncApi\Support\ClassDiscoverer;
use LogicException;
use ReflectionClass;

final class WebhookEventRegistry
{
    /**
     * @var array<class-string, WebhookEventDefinition>|null
     */
    private ?array $defaultDefinitionsByClass = null;

    public function __construct(
        private readonly ClassDiscoverer $classes,
    ) {}

    /**
     * @param  class-string  $class
     */
    public function forClass(string $class): ?WebhookEventDefinition
    {
        return $this->defaultDefinitionsByClass()[$class] ?? null;
    }

    /**
     * @param  list<string>|null  $scanPaths
     * @return list<WebhookEventDefinition>
     */
    public function all(?array $scanPaths = null): array
    {
        $definitions = [];
        $scanPaths ??= $this->scanPaths();

        foreach ($this->classes->classesIn($scanPaths) as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                continue;
            }

            $attributes = $reflection->getAttributes(WebhookEvent::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();

            if (isset($definitions[$attribute->name])) {
                throw new LogicException("Duplicate webhook event name [{$attribute->name}]");
            }

            $definitions[$attribute->name] = new WebhookEventDefinition(
                name: $attribute->name,
                class: $class,
                title: $attribute->title,
                summary: $attribute->summary,
                description: $attribute->description,
                tags: $attribute->tags,
                attribute: $attribute,
            );
        }

        ksort($definitions);

        return array_values($definitions);
    }

    /**
     * @return array<class-string, WebhookEventDefinition>
     */
    private function defaultDefinitionsByClass(): array
    {
        if ($this->defaultDefinitionsByClass !== null) {
            return $this->defaultDefinitionsByClass;
        }

        $definitions = [];

        foreach ($this->all() as $definition) {
            $definitions[$definition->class] = $definition;
        }

        return $this->defaultDefinitionsByClass = $definitions;
    }

    /**
     * @return list<string>
     */
    public static function resolveScanPaths(mixed $webhookScanPaths, mixed $fallbackScanPaths): array
    {
        $scanPaths = $webhookScanPaths ?? $fallbackScanPaths ?? [];

        if (! is_array($scanPaths)) {
            return [];
        }

        return array_values(array_filter($scanPaths, is_string(...)));
    }

    /**
     * @return list<string>
     */
    private function scanPaths(): array
    {
        return self::resolveScanPaths(
            config('spectacular.asyncapi.webhooks.scan_paths'),
            config('spectacular.asyncapi.scan_paths', []),
        );
    }
}
