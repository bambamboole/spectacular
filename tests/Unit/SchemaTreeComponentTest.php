<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\SchemaTree;

it('serializes to the spectacular.schema-tree node', function (): void {
    $node = SchemaTree::make()
        ->forSchema(['type' => 'object'], ['schemas' => []])
        ->jsonSerialize();

    expect($node['type'])->toBe('spectacular.schema-tree')
        ->and($node['props']['schema'])->toBe(['type' => 'object'])
        ->and($node['props']['components'])->toBe(['schemas' => []]);
});
