<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

final class PublicOpenApiDocument
{
    private const array HTTP_METHODS = [
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
    ];

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    public static function create(array $document): ?array
    {
        $paths = $document['paths'] ?? null;

        if (! is_array($paths)) {
            return null;
        }

        $hasInternalOperations = false;

        foreach ($paths as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;

                if (! is_array($operation) || ($operation['x-internal'] ?? false) !== true) {
                    continue;
                }

                unset($pathItem[$method]);
                $hasInternalOperations = true;
            }

            if (self::hasOperations($pathItem)) {
                $paths[$path] = $pathItem;
            } else {
                unset($paths[$path]);
            }
        }

        if (! $hasInternalOperations) {
            return null;
        }

        $document['paths'] = $paths;

        return $document;
    }

    /** @param array<string, mixed> $pathItem */
    private static function hasOperations(array $pathItem): bool
    {
        foreach (self::HTTP_METHODS as $method) {
            if (array_key_exists($method, $pathItem)) {
                return true;
            }
        }

        return false;
    }
}
