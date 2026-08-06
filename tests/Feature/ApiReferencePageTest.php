<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Lattice\ApiReference;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Lattice\Ui\PageSchema;
use Lattice\Core\Enums\PageContainer;
use Workbench\App\Models\Category;
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

it('paginates categories without a redundant total header', function (): void {
    app()->register(WorkbenchServiceProvider::class);

    foreach (range(1, 3) as $category) {
        Category::query()->create(['name' => "Category {$category}"]);
    }

    $this->getJson('/api/categories?per_page=2')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertHeaderMissing('X-Total-Count');
});
