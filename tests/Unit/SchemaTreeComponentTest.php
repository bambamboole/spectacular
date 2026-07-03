<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\SchemaTree;

it('serializes to the spectacular.schema-tree node', function (): void {
    $node = SchemaTree::make()
        ->for(['components' => ['schemas' => []]], '#/components/schemas/Node')
        ->jsonSerialize();

    expect($node['type'])->toBe('spectacular.schema-tree')
        ->and($node['props']['pointer'])->toBe('#/components/schemas/Node')
        ->and($node['props']['document'])->toBe(['components' => ['schemas' => []]]);
});
