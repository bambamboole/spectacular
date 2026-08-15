<?php

declare(strict_types=1);

use Bambamboole\Spectacular\QueryBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Workbench\App\Models\Role;
use Workbench\App\Models\User;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    foreach (range(1, 3) as $number) {
        User::query()->create([
            'name' => "User {$number}",
            'email' => "user{$number}@example.com",
            'password' => 'password',
            'created_at' => "2026-0{$number}-01 00:00:00",
            'updated_at' => "2026-0{$number}-01 00:00:00",
        ]);
    }

    Route::get('api/public-users', fn () => response()->json(
        QueryBuilder::for(User::class)->apiPaginate(),
    ));

    Route::get('api/public-users-list', fn () => response()->json(
        QueryBuilder::for(User::class)->get(),
    ));

    Route::get('api/public-users/{id}', fn (string $id) => response()->json(
        QueryBuilder::for(User::class)->findOrFail($id),
    ));

    Route::get('api/public-user-lookup/{id}', fn (string $id) => response()->json(
        QueryBuilder::for(User::class)->apiFindOrFail($id),
    ));

    Route::get('api/declared-public-users', fn () => response()->json(
        QueryBuilder::for(User::class)
            ->allowedFilters('name')
            ->allowedSorts('name')
            ->allowedIncludes(AllowedInclude::count('roleTotal', 'roles'))
            ->apiPaginate(),
    ));

    Route::get('api/redeclared-public-users', fn () => response()->json(
        QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::scope('created_after'))
            ->allowedSorts('created_at')
            ->apiPaginate(),
    ));

    Route::get('api/role-users', function () {
        $role = Role::query()->create(['name' => 'admin']);
        $role->users()->attach(User::query()->pluck('id'));

        return response()->json(QueryBuilder::for($role->users())->apiPaginate());
    });

    Route::get('api/public-roles', fn () => response()->json(
        QueryBuilder::for(Role::class)->apiPaginate(),
    ));
});

it('allows api filters without an allowedFilters call', function (): void {
    $this->getJson('/api/public-users?filter[created_after]=2026-02-01')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'User 2');
});

it('allows api filters on a forwarded query execution', function (): void {
    $this->getJson('/api/public-users-list?filter[created_before]=2026-01-15')
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'User 1');
});

it('compares with the operator prefixed to a dynamic operator filter value', function (string $value, array $names): void {
    $response = $this->getJson('/api/public-users?filter[created_at]='.urlencode($value))->assertSuccessful();

    expect($response->collect('data')->pluck('name')->all())->toBe($names);
})->with([
    'greater than' => ['>2026-02-01 00:00:00', ['User 3']],
    'greater than or equal' => ['>=2026-02-01 00:00:00', ['User 2', 'User 3']],
    'less than' => ['<2026-02-01 00:00:00', ['User 1']],
    'less than or equal' => ['<=2026-02-01 00:00:00', ['User 1', 'User 2']],
    'no prefix matches exactly' => ['2026-02-01 00:00:00', ['User 2']],
    'comma combines comparisons into a range' => ['>=2026-01-15 00:00:00,<2026-03-01 00:00:00', ['User 2']],
]);

it('allows a non-scope api filter', function (): void {
    $this->getJson('/api/public-users?filter[email]=user2@example.com')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'User 2');
});

it('allows api sorts without an allowedSorts call', function (): void {
    $this->getJson('/api/public-users?sort=-created_at')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'User 3');

    $this->getJson('/api/public-users?sort=updated_at')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'User 1');
});

it('allows api includes without an allowedIncludes call', function (): void {
    $this->getJson('/api/public-users-list?include=roles,rolesCount')
        ->assertSuccessful()
        ->assertJsonPath('0.roles', [])
        ->assertJsonPath('0.roles_count', 0);
});

it('merges api declarations with explicit calls', function (): void {
    $this->getJson('/api/declared-public-users?filter[name]=User&filter[created_after]=2026-03-01&sort=-created_at&include=roles,roleTotal')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'User 3')
        ->assertJsonPath('data.0.roles', [])
        ->assertJsonPath('data.0.roles_count', 0);
});

it('keeps a re-declared api declaration single', function (): void {
    $this->getJson('/api/redeclared-public-users?filter[created_after]=2026-02-01&sort=-created_at')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'User 3');
});

it('rejects unknown api declarations', function (string $uri, string $query): void {
    $this->getJson("{$uri}?{$query}")->assertBadRequest();
})->with([
    'undeclared filter' => ['/api/public-users', 'filter[unknown]=x'],
    'undeclared sort' => ['/api/public-users', 'sort=unknown'],
    'declared filter' => ['/api/declared-public-users', 'filter[unknown]=x'],
    'declared sort' => ['/api/declared-public-users', 'sort=unknown'],
    'undeclared include' => ['/api/public-users', 'include=unknown'],
    'declared include' => ['/api/declared-public-users', 'include=unknown'],
    'forwarded filter' => ['/api/public-users-list', 'filter[unknown]=x'],
    'forwarded sort' => ['/api/public-users-list', 'sort=unknown'],
    'forwarded include' => ['/api/public-users-list', 'include=unknown'],
    'forwarded nested include' => ['/api/public-users-list', 'include=roles.unknown'],
]);

it('allows api includes on a forwarded single-model lookup', function (): void {
    $id = User::query()->where('name', 'User 1')->value('id');

    $this->getJson("/api/public-users/{$id}?include=roles")
        ->assertSuccessful()
        ->assertJsonPath('roles', []);
});

it('rejects an unknown api include on a forwarded single-model lookup', function (): void {
    $id = User::query()->where('name', 'User 1')->value('id');

    $this->getJson("/api/public-users/{$id}?include=unknown")->assertBadRequest();
});

it('allows api includes on a single-result lookup', function (): void {
    $id = User::query()->where('name', 'User 1')->value('id');

    $this->getJson("/api/public-user-lookup/{$id}?include=roles,rolesCount")
        ->assertSuccessful()
        ->assertJsonPath('roles', [])
        ->assertJsonPath('roles_count', 0);
});

it('rejects collection parameters on a single-result lookup', function (string $query): void {
    $id = User::query()->where('name', 'User 1')->value('id');

    $this->getJson("/api/public-user-lookup/{$id}?{$query}")->assertBadRequest();
})->with([
    'unknown include' => 'include=unknown',
    'declared filter' => 'filter[created_after]=2026-01-01',
    'unknown filter' => 'filter[unknown]=x',
    'declared sort' => 'sort=created_at',
    'unknown sort' => 'sort=unknown',
]);

it('allows api filters on a relation subject', function (): void {
    $this->getJson('/api/role-users?filter[created_after]=2026-02-01')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('leaves models without api declarations untouched', function (): void {
    $this->getJson('/api/public-roles?filter[name]=x')
        ->assertSuccessful();
});
