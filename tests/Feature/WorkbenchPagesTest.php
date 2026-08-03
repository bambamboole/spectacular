<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Ui\Components\ContainerComponent;
use Workbench\App\Pages\ApiReferenceIndexPage;
use Workbench\App\Pages\StackedApiReferencePage;

it('links to both API reference layouts from the workbench index', function (): void {
    $root = app(ApiReferenceIndexPage::class)->render(PageSchema::make())->renderable()[0];

    if (! $root instanceof ContainerComponent) {
        throw new UnexpectedValueException('Expected the index root to contain the layout buttons.');
    }

    $buttons = $root->descendants();

    expect($buttons)->toHaveCount(2)
        ->and($buttons[0]->jsonSerialize()['props'])->toMatchArray(['label' => 'Sidebar', 'href' => '/docs'])
        ->and($buttons[1]->jsonSerialize()['props'])->toMatchArray(['label' => 'Stacked', 'href' => '/docs/stacked']);
});

it('renders the stacked API reference workbench page', function (): void {
    $schema = app(StackedApiReferencePage::class)->render(PageSchema::make(), app(Request::class));
    $reference = $schema->renderable()[0];

    expect($reference)->toBeInstanceOf(ApiReference::class)
        ->and($reference->jsonSerialize()['props']['layout'])->toBe('stacked');
});
