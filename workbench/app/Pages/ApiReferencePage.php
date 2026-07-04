<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Http\Request;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Http\Page;

#[AsPage(route: 'reference', name: 'reference')]
final class ApiReferencePage extends Page
{
    public function render(PageSchema $schema, Request $request): PageSchema
    {
        /** @var array<string, mixed> $document */
        $document = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return $schema->schema([ApiReference::make()->spec($document)]);
    }
}
