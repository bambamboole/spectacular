<?php

declare(strict_types=1);

namespace Workbench\App\Pages;

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Http\Request;
use Lattice\Core\Attributes\AsPage;
use Lattice\Core\Enums\PageContainer;
use Lattice\Http\Page;
use Lattice\Ui\PageSchema;

#[AsPage(route: '/', name: 'home')]
final class ApiReferencePage extends Page
{
    public function container(): PageContainer
    {
        return PageContainer::Default;
    }

    public function render(PageSchema $schema, Request $request): PageSchema
    {
        /** @var array<string, mixed> $document */
        $document = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/fixtures/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $document['servers'] = [['url' => '/api']];

        return $schema->schema([
            ApiReference::make()
                ->spec($document)
                ->token((string) config('services.spectacular.demo_token', 'workbench-token')),
        ]);
    }
}
