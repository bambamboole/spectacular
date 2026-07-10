<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;

it('serializes to the spectacular.api-reference node with a url', function (): void {
    $node = ApiReference::make()
        ->url('/openapi.json')
        ->jsonSerialize();

    expect($node['type'])->toBe('spectacular.api-reference')
        ->and($node['props']['url'])->toBe('/openapi.json');
});

it('serializes to the spectacular.api-reference node with an inline spec', function (): void {
    $node = ApiReference::make()
        ->spec(['openapi' => '3.0.0'])
        ->jsonSerialize();

    expect($node['type'])->toBe('spectacular.api-reference')
        ->and($node['props']['spec'])->toBe(['openapi' => '3.0.0']);
});

it('defaults to the current no-modifier behaviour', function (): void {
    $props = ApiReference::make()->jsonSerialize()['props'];

    expect($props['operation'])->toBeNull()
        ->and($props['tags'])->toBeNull()
        ->and($props['hideNav'])->toBeFalse()
        ->and($props['layout'])->toBe('sidebar')
        ->and($props['defaultOperation'])->toBeNull()
        ->and($props['hideHeader'])->toBeFalse()
        ->and($props['title'])->toBeNull()
        ->and($props['expandDepth'])->toBe(0);
});

it('sets the operation prop for embedding a single endpoint', function (): void {
    $node = ApiReference::make()->operation('get-users-id')->jsonSerialize();

    expect($node['props']['operation'])->toBe('get-users-id');
});

it('normalises a single tag string into a list', function (): void {
    $node = ApiReference::make()->tag('Users')->jsonSerialize();

    expect($node['props']['tags'])->toBe(['Users']);
});

it('keeps a list of tags as given', function (): void {
    $node = ApiReference::make()->tag(['A', 'B'])->jsonSerialize();

    expect($node['props']['tags'])->toBe(['A', 'B']);
});

it('sets hideNav', function (): void {
    $node = ApiReference::make()->hideNav()->jsonSerialize();

    expect($node['props']['hideNav'])->toBeTrue();
});

it('sets the stacked layout', function (): void {
    $node = ApiReference::make()->layout('stacked')->jsonSerialize();

    expect($node['props']['layout'])->toBe('stacked');
});

it('throws for an invalid layout', function (): void {
    ApiReference::make()->layout('bogus');
})->throws(InvalidArgumentException::class);

it('sets the defaultOperation prop', function (): void {
    $node = ApiReference::make()->defaultOperation('get-users-id')->jsonSerialize();

    expect($node['props']['defaultOperation'])->toBe('get-users-id');
});

it('sets hideHeader', function (): void {
    $node = ApiReference::make()->hideHeader()->jsonSerialize();

    expect($node['props']['hideHeader'])->toBeTrue();
});

it('sets the title prop', function (): void {
    $node = ApiReference::make()->title('My API')->jsonSerialize();

    expect($node['props']['title'])->toBe('My API');
});

it('sets the expandDepth prop', function (): void {
    $node = ApiReference::make()->expandDepth(2)->jsonSerialize();

    expect($node['props']['expandDepth'])->toBe(2);
});
