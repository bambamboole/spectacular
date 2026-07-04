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
