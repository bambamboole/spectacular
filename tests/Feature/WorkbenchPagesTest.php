<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Http\Request;
use Lattice\Lattice\Attributes\AsPage;
use Lattice\Lattice\Core\PageSchema;
use Workbench\App\Pages\ApiReferencePage;

it('renders the grouped API reference on the home page', function (): void {
    $page = (new ReflectionClass(ApiReferencePage::class))->getAttributes(AsPage::class)[0]->newInstance();
    $schema = app(ApiReferencePage::class)->render(PageSchema::make(), app(Request::class));
    $reference = $schema->renderable()[0];
    $props = $reference->jsonSerialize()['props'];

    expect($page->route)->toBe('/')
        ->and($reference)->toBeInstanceOf(ApiReference::class)
        ->and($props)->not->toHaveKeys(['hideNav', 'layout']);
});
