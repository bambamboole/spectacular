<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;
use Workbench\App\Pages\ApiReferencePage;
use Workbench\App\Providers\WorkbenchServiceProvider;

it('uses the current workbench origin for API requests', function (): void {
    $schema = app(ApiReferencePage::class)->render(PageSchema::make(), app(Request::class));
    $reference = $schema->renderable()[0];

    expect($reference)->toBeInstanceOf(ApiReference::class)
        ->and($reference->jsonSerialize()['props']['spec']['servers'])->toBe([
            ['url' => '/api'],
        ]);
});

it('accepts the bearer token configured by the API reference', function (): void {
    app()->register(WorkbenchServiceProvider::class);

    $this->withHeader('Authorization', 'Bearer workbench-token')
        ->postJson('/api/users')
        ->assertUnprocessable();
});
