<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Illuminate\Http\Request;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\Components\Heading;
use Lattice\Lattice\Core\Components\Section;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Http\Page;

#[AsPage(route: 'docs', name: 'docs')]
final class ApiDocsPage extends Page
{
    public function render(PageSchema $schema, Request $request): PageSchema
    {
        return $schema->schema([
            Section::make('API Documentation')->schema([
                Heading::make('It works'),
            ]),
        ]);
    }
}
