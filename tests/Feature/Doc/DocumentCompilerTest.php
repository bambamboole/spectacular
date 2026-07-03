<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Adapters\OpenApiAdapter;
use Bambamboole\Spectacular\Doc\Lattice\DocumentCompiler;

it('compiles the document into a lattice node tree with nav and operation shells', function (): void {
    $doc = (new OpenApiAdapter)->adapt(json_decode((string) file_get_contents(dirname(__DIR__, 3).'/workbench/fixtures/openapi.json'), true, flags: JSON_THROW_ON_ERROR));

    $nodes = (new DocumentCompiler)->compile($doc);
    $json = json_decode(json_encode($nodes, JSON_THROW_ON_ERROR), true);

    // a schema-tree node appears for the users response body, carrying the components bundle
    $flat = json_encode($json);
    expect($flat)->toContain('spectacular.schema-tree')
        ->and($flat)->toContain('GET')
        ->and($flat)->toContain('/users')
        ->and($flat)->toContain('UserResource')
        ->and($flat)->toContain('id=\"get-users\"');
});

/**
 * @param  array<string, mixed>  $node
 * @return list<array<string, mixed>>
 */
function findNodesByType(array $node, string $type): array
{
    $matches = [];

    if (($node['type'] ?? null) === $type) {
        $matches[] = $node;
    }

    foreach ($node['schema'] ?? [] as $child) {
        if (is_array($child)) {
            $matches = [...$matches, ...findNodesByType($child, $type)];
        }
    }

    return $matches;
}

it('surfaces a $ref-resolved response description above its tab body', function (): void {
    $doc = (new OpenApiAdapter)->adapt(json_decode((string) file_get_contents(dirname(__DIR__, 3).'/workbench/fixtures/openapi.json'), true, flags: JSON_THROW_ON_ERROR));

    $nodes = (new DocumentCompiler)->compile($doc);
    $json = json_decode(json_encode($nodes, JSON_THROW_ON_ERROR), true);

    $tabs = findNodesByType($json[0], 'tab');
    $notFoundTab = collect($tabs)->first(fn (array $tab): bool => ($tab['props']['value'] ?? null) === '404');

    expect($notFoundTab)->not->toBeNull();

    $tabTexts = findNodesByType($notFoundTab, 'text');
    $tabSchemaTrees = findNodesByType($notFoundTab, 'spectacular.schema-tree');

    expect(collect($tabTexts)->pluck('props.text'))->toContain('Not found')
        ->and($tabSchemaTrees)->not->toBeEmpty();
});
