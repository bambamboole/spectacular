<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Adapters\OpenApiAdapter;
use Bambamboole\Spectacular\Doc\Lattice\DocumentCompiler;
use Bambamboole\Spectacular\Doc\Model\ApiDocument;

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

    $tabs = findNodesByType($json[1], 'tab');
    $notFoundTab = collect($tabs)->first(fn (array $tab): bool => ($tab['props']['value'] ?? null) === '404');

    expect($notFoundTab)->not->toBeNull();

    $tabTexts = findNodesByType($notFoundTab, 'text');
    $tabSchemaTrees = findNodesByType($notFoundTab, 'spectacular.schema-tree');

    expect(collect($tabTexts)->pluck('props.text'))->toContain('Not found')
        ->and($tabSchemaTrees)->not->toBeEmpty();
});

it('renders an info header with the API title, version, and description', function (): void {
    $doc = new ApiDocument(
        format: 'openapi',
        formatVersion: '3.1.0',
        info: ['title' => 'Widget API', 'version' => '2.3.0', 'description' => 'The widget service.'],
        servers: [],
        groups: [],
        operations: [],
        components: ['schemas' => []],
    );

    $nodes = (new DocumentCompiler)->compile($doc);
    $flat = json_encode(json_decode(json_encode($nodes, JSON_THROW_ON_ERROR), true));

    expect($flat)->toContain('Widget API')
        ->and($flat)->toContain('2.3.0')
        ->and($flat)->toContain('The widget service.');
});

it('omits the description node when the info block has none', function (): void {
    $doc = new ApiDocument(
        format: 'openapi',
        formatVersion: '3.1.0',
        info: ['title' => 'Widget API', 'version' => '2.3.0'],
        servers: [],
        groups: [],
        operations: [],
        components: ['schemas' => []],
    );

    $nodes = (new DocumentCompiler)->compile($doc);
    $json = json_decode(json_encode($nodes, JSON_THROW_ON_ERROR), true);

    $texts = findNodesByType($json[0], 'text');

    expect($texts)->toBeEmpty();
});
