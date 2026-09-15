<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

final class GroupedOpenApiDocuments
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
     * @return array<string|int, array<string, mixed>>
     */
    public static function create(array $document): array
    {
        $groups = [];

        foreach ($document['paths'] ?? [] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            $metadata = array_diff_key($pathItem, array_flip(self::HTTP_METHODS));

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                $group = is_array($operation) ? ($operation['x-group'] ?? null) : null;

                if (! is_string($group)) {
                    continue;
                }

                $groups[$group] ??= array_replace($document, ['paths' => []]);
                $groups[$group]['paths'][$path] ??= $metadata;
                $groups[$group]['paths'][$path][$method] = $operation;
            }
        }

        return $groups;
    }
}
