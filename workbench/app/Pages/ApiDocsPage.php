<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Bambamboole\Spectacular\Doc\Adapters\OpenApiAdapter;
use Bambamboole\Spectacular\Doc\Lattice\DocumentCompiler;
use Illuminate\Http\Request;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Http\Page;

#[AsPage(route: 'docs', name: 'docs')]
final class ApiDocsPage extends Page
{
    public function render(PageSchema $schema, Request $request): PageSchema
    {
        /** @var array<string, mixed> $document */
        $document = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $api = (new OpenApiAdapter)->adapt($document);

        return $schema->schema((new DocumentCompiler)->compile($api));
    }
}
