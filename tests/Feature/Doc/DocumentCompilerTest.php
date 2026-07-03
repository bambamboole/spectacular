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
        ->and($flat)->toContain('UserResource');
});
