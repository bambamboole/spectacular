<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\Doc\Adapters;

use Bambamboole\Spectacular\Doc\Model\ApiDocument;
use Bambamboole\Spectacular\Doc\Model\ApiGroup;
use Bambamboole\Spectacular\Doc\Model\Contract;
use Bambamboole\Spectacular\Doc\Model\HttpFacet;
use Bambamboole\Spectacular\Doc\Model\Operation;
use Bambamboole\Spectacular\Doc\Model\OperationKind;
use Bambamboole\Spectacular\Doc\Model\Param;
use Bambamboole\Spectacular\Doc\Model\ParamGroup;
use Illuminate\Support\Str;

final class OpenApiAdapter
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

    private const PARAM_LOCATION_ORDER = ['path', 'query', 'header', 'cookie'];

    private const DEFAULT_GROUP_TITLE = 'Default';

    /**
     * @param  array<string, mixed>  $document
     */
    public function adapt(array $document): ApiDocument
    {
        $operations = $this->buildOperations($document['paths'] ?? []);

        return new ApiDocument(
            format: 'openapi',
            formatVersion: (string) ($document['openapi'] ?? '3.1.0'),
            info: $document['info'] ?? [],
            servers: array_values($document['servers'] ?? []),
            groups: $this->buildGroups($operations),
            operations: $operations,
            components: $document['components'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $paths
     * @return list<Operation>
     */
    private function buildOperations(array $paths): array
    {
        $operations = [];

        foreach ($paths as $path => $pathItem) {
            $sharedParameters = $pathItem['parameters'] ?? [];

            foreach (self::HTTP_METHODS as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }

                $operations[] = $this->buildOperation($path, $method, $pathItem[$method], $sharedParameters);
            }
        }

        usort($operations, fn (Operation $a, Operation $b) => $this->sortKey($a) <=> $this->sortKey($b));

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  list<array<string, mixed>>  $sharedParameters
     */
    private function buildOperation(string $path, string $method, array $operation, array $sharedParameters): Operation
    {
        $operationParameters = $operation['parameters'] ?? [];

        return new Operation(
            id: $this->operationId($method, $path),
            kind: OperationKind::Http,
            title: $this->title($operation, $method, $path),
            summary: $operation['summary'] ?? null,
            description: $operation['description'] ?? null,
            tags: array_values($operation['tags'] ?? []),
            deprecated: (bool) ($operation['deprecated'] ?? false),
            paramGroups: $this->buildParamGroups($sharedParameters, $operationParameters),
            responses: $this->buildResponses($operation['responses'] ?? []),
            facet: new HttpFacet(strtoupper($method), $path, $operation['operationId'] ?? null),
        );
    }

    private function operationId(string $method, string $path): string
    {
        $slug = trim(str_replace(['/', '{', '}'], ['-', '', ''], $path), '-');

        return $slug === '' ? strtolower($method).'-root' : strtolower($method).'-'.$slug;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function title(array $operation, string $method, string $path): string
    {
        if (is_string($operation['operationId'] ?? null) && $operation['operationId'] !== '') {
            return $operation['operationId'];
        }

        if (is_string($operation['summary'] ?? null) && $operation['summary'] !== '') {
            return $operation['summary'];
        }

        return strtoupper($method).' '.$path;
    }

    /**
     * @param  list<array<string, mixed>>  $sharedParameters
     * @param  list<array<string, mixed>>  $operationParameters
     * @return list<ParamGroup>
     */
    private function buildParamGroups(array $sharedParameters, array $operationParameters): array
    {
        $merged = [];

        foreach ([$sharedParameters, $operationParameters] as $parameters) {
            foreach ($parameters as $parameter) {
                $key = ($parameter['in'] ?? '').'::'.($parameter['name'] ?? '');
                $merged[$key] = $parameter;
            }
        }

        $buckets = [];
        foreach ($merged as $parameter) {
            $buckets[$parameter['in']][] = $this->buildParam($parameter);
        }

        $groups = [];
        foreach (self::PARAM_LOCATION_ORDER as $location) {
            if ($buckets[$location] ?? null) {
                $groups[] = new ParamGroup($location, $buckets[$location]);
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function buildParam(array $parameter): Param
    {
        return new Param(
            name: $parameter['name'],
            location: $parameter['in'],
            required: (bool) ($parameter['required'] ?? false),
            deprecated: (bool) ($parameter['deprecated'] ?? false),
            description: $parameter['description'] ?? null,
            schema: $parameter['schema'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $responses
     * @return list<Contract>
     */
    private function buildResponses(array $responses): array
    {
        $contracts = [];

        foreach ($responses as $status => $response) {
            $description = $response['description'] ?? null;
            $content = $response['content'] ?? [];

            if ($content === []) {
                $contracts[] = new Contract('response', (string) $status, null, [], $description);

                continue;
            }

            foreach ($content as $mediaType => $mediaTypeObject) {
                $contracts[] = new Contract('response', (string) $status, $mediaType, $mediaTypeObject['schema'] ?? [], $description);
            }
        }

        return $contracts;
    }

    /**
     * @param  list<Operation>  $operations
     * @return list<ApiGroup>
     */
    private function buildGroups(array $operations): array
    {
        /** @var array<string, list<string>> $operationIdsByTag */
        $operationIdsByTag = [];

        foreach ($operations as $operation) {
            foreach ($this->groupTags($operation) as $tag) {
                $operationIdsByTag[$tag][] = $operation->id;
            }
        }

        $groups = [];
        foreach ($operationIdsByTag as $tag => $operationIds) {
            $groups[] = new ApiGroup(Str::slug($tag), $tag, null, $operationIds);
        }

        return $groups;
    }

    private function primaryTag(Operation $operation): string
    {
        return $operation->tags[0] ?? self::DEFAULT_GROUP_TITLE;
    }

    /**
     * @return list<string>
     */
    private function groupTags(Operation $operation): array
    {
        return $operation->tags === [] ? [self::DEFAULT_GROUP_TITLE] : $operation->tags;
    }

    private function sortKey(Operation $operation): string
    {
        $method = $operation->facet instanceof HttpFacet ? $operation->facet->method : '';
        $path = $operation->facet instanceof HttpFacet ? $operation->facet->path : '';

        return $this->primaryTag($operation).'::'.$path.'::'.$method;
    }
}
