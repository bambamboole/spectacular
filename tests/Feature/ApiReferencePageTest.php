<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Lattice\Lattice\Core\PageSchema;
use Lattice\Lattice\Ui\Enums\PageContainer;
use Workbench\App\Models\User;
use Workbench\App\Pages\ApiReferencePage;
use Workbench\App\Providers\WorkbenchServiceProvider;

uses(LazilyRefreshDatabase::class);

it('uses the full-width page container', function (): void {
    expect(app(ApiReferencePage::class)->container())->toBe(PageContainer::Default);
});

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

it('stores users from the API reference', function (): void {
    app()->register(WorkbenchServiceProvider::class);

    $this->withHeader('Authorization', 'Bearer workbench-token')
        ->postJson('/api/users', [
            'name' => 'Taylor Otwell',
            'email' => 'taylor@example.com',
            'password' => 'secret-password',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.attributes.email', 'taylor@example.com');

    $user = User::query()->where('email', 'taylor@example.com')->firstOrFail();

    expect(Hash::check('secret-password', (string) $user->getAttribute('password')))->toBeTrue();
});
