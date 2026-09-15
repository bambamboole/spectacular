<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Transformers;

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;
use Bambamboole\Spectacular\OpenApi\ExplicitOperation;
use Bambamboole\Spectacular\OpenApi\Types\ExplicitSchema;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationBuilder;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use InvalidArgumentException;

final readonly class DocumentsEndpoints implements DocumentTransformer
{
    public function __construct(private OperationBuilder $builder, private TypeTransformer $typeTransformer) {}

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $apiName = get_object_vars($context->config)['name'] ?? null;
        $isDefault = $apiName !== null ? $apiName === Scramble::DEFAULT_API : $context->config === Scramble::getGeneratorConfig(Scramble::DEFAULT_API);
        $classes = $context->config->get('spectacular.endpoints', $isDefault ? config('spectacular.openapi.endpoints', []) : []);
        $seen = [];
        $fragments = [];

        foreach ($classes as $class) {
            $definition = app($class);
            if (! $definition instanceof EndpointDefinition) {
                throw new InvalidArgumentException("Endpoint definition [$class] must implement ".EndpointDefinition::class.'.');
            }

            foreach ($definition->schemas() as $name => $schema) {
                if (! preg_match('/^[a-zA-Z0-9._-]+$/', $name) || ($document->components->hasSchema($name) || array_key_exists($name, $document->components->toArray()['schemas'] ?? []))) {
                    throw new InvalidArgumentException("Duplicate or invalid endpoint schema [$name].");
                }
                $document->components->addSchema($name, new ExplicitSchema($schema));
                $fragments[] = $schema;
            }

            foreach ($definition->endpoints() as $endpoint) {
                $route = $this->resolve($endpoint);
                $key = $route->uri().':'.$endpoint->method;
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException("Duplicate endpoint [$key].");
                }
                $seen[$key] = true;
                foreach ($this->paths($route->uri()) as $uri) {
                    $resolvedKey = '/'.$context->config->apiPath()->stripPrefix($uri).':'.$endpoint->method;
                    if (isset($seen[$resolvedKey])) {
                        throw new InvalidArgumentException("Duplicate resolved endpoint [$resolvedKey].");
                    }
                    $seen[$resolvedKey] = true;
                }
                $this->apply($document, $context, $route, $endpoint);
                $fragments[] = $endpoint->operation;
            }
        }

        if ($fragments !== []) {
            $serialized = $document->toArray();
            foreach ($fragments as $fragment) {
                $this->validateReferences($fragment, $serialized);
            }
        }
    }

    private function resolve(Endpoint $endpoint): Route
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())->filter(fn (Route $route): bool => ($endpoint->named ? $route->getName() : $route->uri()) === $endpoint->target);
        $matching = $routes->filter(fn (Route $route): bool => in_array(strtoupper($endpoint->method), $route->methods(), true));
        if ($matching->count() !== 1) {
            throw new InvalidArgumentException("Endpoint [{$endpoint->method} {$endpoint->target}] must match exactly one registered route and method.");
        }

        return $matching->first();
    }

    private function apply(OpenApi $document, OpenApiContext $context, Route $route, Endpoint $endpoint): void
    {
        $base = null;
        foreach ($document->paths as $path) {
            $existing = $path->operations[$endpoint->method] ?? null;
            if ($existing?->getAttribute('spectacularRoute') === $route) {
                $base = clone $existing;
                if ($base->servers === []) {
                    $base->servers = $path->servers;
                }
                unset($path->operations[$endpoint->method]);
            }
        }
        $base ??= $this->infer($route, $endpoint, $document, $context);
        $document->paths = array_values(array_filter($document->paths, fn (Path $path): bool => $path->operations !== []));

        $uris = $this->paths($route->uri());
        foreach ($uris as $uri) {
            $operation = clone $base;
            $operation->path = $uri;
            $operation->method = $endpoint->method;
            $operation->operationId = $base->operationId ?? ($route->getName() ?? str_replace('/', '.', $route->uri())).'.'.$endpoint->method;
            if (count($uris) > 1) {
                $operation->operationId .= '.'.substr(sha1($uri), 0, 8);
            }
            preg_match_all('/\{([^}]+)\}/', $uri, $matches);
            $names = $matches[1];
            $pathParameters = [];
            foreach ($operation->parameters as $parameter) {
                if ($parameter instanceof Parameter && $parameter->in === 'path') {
                    $pathParameters[$parameter->name] = $parameter;
                }
            }
            $operation->parameters = array_values(array_filter($operation->parameters, fn ($parameter): bool => ! $parameter instanceof Parameter || $parameter->in !== 'path'));
            foreach ($names as $name) {
                $operation->parameters[] = $pathParameters[$name] ?? Parameter::make($name, 'path')->required(true)->setSchema(Schema::fromType(new StringType));
            }
            $overrides = $endpoint->operation;
            if (isset($overrides['operationId']) && count($uris) > 1) {
                $overrides['operationId'] .= '.'.substr(sha1($uri), 0, 8);
            }
            if (isset($overrides['parameters'])) {
                $overrides['parameters'] = array_values(array_filter($overrides['parameters'], fn (array $parameter): bool => ($parameter['in'] ?? null) !== 'path' || in_array($parameter['name'] ?? '', $names, true)));
                foreach ($overrides['parameters'] as &$parameter) {
                    if (($parameter['in'] ?? null) === 'path' && (($parameter['required'] ?? true) !== true || ($parameter['x-remove'] ?? false))) {
                        throw new InvalidArgumentException('OpenAPI path parameters must be required.');
                    }
                    if (($parameter['in'] ?? null) === 'path') {
                        $parameter['required'] = true;
                    }
                }
                unset($parameter);
            }
            if (! $context->config->apiPath()->matches($uri) && ! isset($overrides['servers'])) {
                $operation->servers = [new Server($route->getDomain() ? request()->getScheme().'://'.$route->getDomain() : url('/'))];
            }
            $explicit = new ExplicitOperation($operation, $overrides);
            if (($explicit->toArray()['responses'] ?? []) === []) {
                throw new InvalidArgumentException("Endpoint [{$endpoint->method} {$endpoint->target}] requires at least one response.");
            }
            $resolvedPath = $context->config->apiPath()->stripPrefix($uri);
            foreach ($document->paths as $path) {
                if ($path->path === $resolvedPath && isset($path->operations[$endpoint->method])) {
                    throw new InvalidArgumentException("Endpoint [{$endpoint->method} {$endpoint->target}] collides with an existing operation at [/$resolvedPath].");
                }
            }
            $document->addPath(Path::make($resolvedPath)->addOperation($explicit));
        }
    }

    private function infer(Route $route, Endpoint $endpoint, OpenApi $document, OpenApiContext $context): Operation
    {
        $arguments = [
            'routeInfo' => new RouteInfo($route, $endpoint->method),
            'openApi' => $document,
            'config' => $context->config,
            'typeTransformer' => $this->typeTransformer,
        ];
        if (array_key_exists('proNudge', get_object_vars($context))) {
            $arguments['proNudge'] = get_object_vars($context)['proNudge'];
        }

        return app()->call([$this->builder, 'build'], $arguments);
    }

    /** @return list<string> */
    private function paths(string $uri): array
    {
        $paths = [''];
        foreach (explode('/', $uri) as $segment) {
            if (preg_match('/^\{[^}]+\?\}$/', $segment)) {
                $paths[] = end($paths).'/'.str_replace('?', '', $segment);
            } else {
                $paths = array_map(fn (string $path): string => $path.'/'.$segment, $paths);
            }
        }

        return array_map(fn (string $path): string => trim($path, '/'), $paths);
    }

    /**
     * @param  array<mixed>  $fragment
     * @param  array<string, mixed>  $document
     */
    private function validateReferences(array $fragment, array $document): void
    {
        foreach ($fragment as $key => $value) {
            if (in_array($key, ['example', 'examples', 'default', 'enum', 'const'], true)) {
                continue;
            }
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/')) {
                $target = $document;
                foreach (explode('/', substr($value, 2)) as $part) {
                    $part = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($part));
                    if (! is_array($target) || ! array_key_exists($part, $target)) {
                        throw new InvalidArgumentException("Unresolved endpoint reference [$value].");
                    }
                    $target = $target[$part];
                }
            } elseif (is_array($value)) {
                $this->validateReferences($value, $document);
            }
        }
    }
}
