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
     * @return list<WebhookEventDefinition>
     */
    public function all(): array
    {
        $definitions = [];
        $scanPaths = $this->scanPaths();
        $realScanPaths = $this->realScanPaths($scanPaths);

        foreach ($this->classes->classesIn($scanPaths) as $class) {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable() || ! $this->isDirectlyInScanPaths($reflection, $realScanPaths)) {
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

    /**
     * @param  list<string>  $scanPaths
     * @return list<string>
     */
    private function realScanPaths(array $scanPaths): array
    {
        return array_values(array_filter(
            array_map(fn (string $path): string|false => realpath($path), $scanPaths),
            is_string(...),
        ));
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     * @param  list<string>  $scanPaths
     */
    private function isDirectlyInScanPaths(ReflectionClass $reflection, array $scanPaths): bool
    {
        $file = $reflection->getFileName();

        if (! is_string($file)) {
            return false;
        }

        $directory = realpath(dirname($file));

        if ($directory === false) {
            return false;
        }

        return in_array($directory, $scanPaths, true);
    }
}
