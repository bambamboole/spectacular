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
        $componentsResponses = $document['components']['responses'] ?? [];
        $componentsRequestBodies = $document['components']['requestBodies'] ?? [];
        $operations = $this->buildOperations($document['paths'] ?? [], $componentsResponses, $componentsRequestBodies);

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
     * @param  array<string, array<string, mixed>>  $componentsResponses
     * @param  array<string, array<string, mixed>>  $componentsRequestBodies
     * @return list<Operation>
     */
    private function buildOperations(array $paths, array $componentsResponses, array $componentsRequestBodies): array
    {
        $operations = [];

        foreach ($paths as $path => $pathItem) {
            $sharedParameters = $pathItem['parameters'] ?? [];

            foreach (self::HTTP_METHODS as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }

                $operations[] = $this->buildOperation($path, $method, $pathItem[$method], $sharedParameters, $componentsResponses, $componentsRequestBodies);
            }
        }

        usort($operations, fn (Operation $a, Operation $b) => $this->sortKey($a) <=> $this->sortKey($b));

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  list<array<string, mixed>>  $sharedParameters
     * @param  array<string, array<string, mixed>>  $componentsResponses
     * @param  array<string, array<string, mixed>>  $componentsRequestBodies
     */
    private function buildOperation(string $path, string $method, array $operation, array $sharedParameters, array $componentsResponses, array $componentsRequestBodies): Operation
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
            responses: $this->buildResponses($operation['responses'] ?? [], $componentsResponses),
            requests: $this->buildRequests($operation, $componentsRequestBodies),
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
     * @param  array<string, array<string, mixed>>  $componentsResponses
     * @return list<Contract>
     */
    private function buildResponses(array $responses, array $componentsResponses): array
    {
        $contracts = [];

        foreach ($responses as $status => $response) {
            $response = $this->resolveResponse($response, $componentsResponses);
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
     * @param  array<string, mixed>  $response
     * @param  array<string, array<string, mixed>>  $componentsResponses
     * @return array<string, mixed>
     */
    private function resolveResponse(array $response, array $componentsResponses): array
    {
        $ref = $response['$ref'] ?? null;

        if (! is_string($ref)) {
            return $response;
        }

        $name = Str::afterLast($ref, '/');

        return $componentsResponses[$name] ?? ['description' => $name, 'content' => []];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, array<string, mixed>>  $componentsRequestBodies
     * @return list<Contract>
     */
    private function buildRequests(array $operation, array $componentsRequestBodies): array
    {
        $requestBody = $operation['requestBody'] ?? null;

        if (! is_array($requestBody) || $requestBody === []) {
            return [];
        }

        $requestBody = $this->resolveRequestBody($requestBody, $componentsRequestBodies);
        $description = $requestBody['description'] ?? null;
        $content = $requestBody['content'] ?? [];

        $contracts = [];
        foreach ($content as $mediaType => $mediaTypeObject) {
            $contracts[] = new Contract('request', null, $mediaType, $mediaTypeObject['schema'] ?? [], $description);
        }

        return $contracts;
    }

    /**
     * @param  array<string, mixed>  $requestBody
     * @param  array<string, array<string, mixed>>  $componentsRequestBodies
     * @return array<string, mixed>
     */
    private function resolveRequestBody(array $requestBody, array $componentsRequestBodies): array
    {
        $ref = $requestBody['$ref'] ?? null;

        if (! is_string($ref)) {
            return $requestBody;
        }

        $name = Str::afterLast($ref, '/');

        return $componentsRequestBodies[$name] ?? ['content' => []];
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
