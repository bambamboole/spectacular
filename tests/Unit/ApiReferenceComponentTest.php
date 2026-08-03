<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;

it('serializes the spectacular.api-reference node', function (): void {
    $node = ApiReference::make()->url('/openapi.json')->jsonSerialize();

    expect($node['type'])->toBe('spectacular.api-reference')
        ->and($node['props']['url'])->toBe('/openapi.json');
});

it('defaults to the unmodified reference', function (): void {
    expect(ApiReference::make()->jsonSerialize()['props'])->toMatchArray([
        'operation' => null,
        'tags' => null,
        'hideNav' => false,
        'layout' => 'sidebar',
        'defaultOperation' => null,
        'hideHeader' => false,
        'title' => null,
        'expandDepth' => 0,
        'token' => null,
    ]);
});

it('serializes fluent options', function (Closure $configure, string $property, mixed $expected): void {
    $props = $configure(ApiReference::make())->jsonSerialize()['props'];

    expect($props[$property])->toBe($expected);
})->with([
    'inline spec' => [fn (ApiReference $reference): ApiReference => $reference->spec(['openapi' => '3.0.0']), 'spec', ['openapi' => '3.0.0']],
    'token' => [fn (ApiReference $reference): ApiReference => $reference->token('secret-token'), 'token', 'secret-token'],
    'operation' => [fn (ApiReference $reference): ApiReference => $reference->operation('get-users-id'), 'operation', 'get-users-id'],
    'hidden navigation' => [fn (ApiReference $reference): ApiReference => $reference->hideNav(), 'hideNav', true],
    'stacked layout' => [fn (ApiReference $reference): ApiReference => $reference->layout('stacked'), 'layout', 'stacked'],
    'default operation' => [fn (ApiReference $reference): ApiReference => $reference->defaultOperation('get-users-id'), 'defaultOperation', 'get-users-id'],
    'hidden header' => [fn (ApiReference $reference): ApiReference => $reference->hideHeader(), 'hideHeader', true],
    'title' => [fn (ApiReference $reference): ApiReference => $reference->title('My API'), 'title', 'My API'],
    'expand depth' => [fn (ApiReference $reference): ApiReference => $reference->expandDepth(2), 'expandDepth', 2],
]);

it('normalizes tag input', function (string|array $tags, array $expected): void {
    expect(ApiReference::make()->tag($tags)->jsonSerialize()['props']['tags'])->toBe($expected);
})->with([
    'single tag' => ['Users', ['Users']],
    'tag list' => [['A', 'B'], ['A', 'B']],
]);

it('rejects an invalid layout', function (): void {
    ApiReference::make()->layout('bogus');
})->throws(InvalidArgumentException::class);
