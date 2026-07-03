<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Webhooks;

use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Bambamboole\Spectacular\AsyncApi\Support\ClassDiscoverer;
use LogicException;
use ReflectionClass;

final readonly class WebhookEventRegistry
{
    public function __construct(
        private ClassDiscoverer $classes,
    ) {}

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
     * @return list<string>
     */
    private function scanPaths(): array
    {
        $scanPaths = config('spectacular.asyncapi.webhooks.scan_paths');

        if ($scanPaths === null) {
            $scanPaths = config('spectacular.asyncapi.scan_paths', []);
        }

        if (! is_array($scanPaths)) {
            return [];
        }

        return array_values(array_filter($scanPaths, is_string(...)));
    }
}
