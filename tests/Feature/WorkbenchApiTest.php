<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\Category;
use Workbench\App\Models\User;
use Workbench\App\Providers\WorkbenchServiceProvider;

uses(LazilyRefreshDatabase::class);

it('accepts the configured bearer token', function (): void {
    app()->register(WorkbenchServiceProvider::class);

    $this->withHeader('Authorization', 'Bearer workbench-token')
        ->postJson('/api/users')
        ->assertUnprocessable();
});

it('stores users', function (): void {
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
