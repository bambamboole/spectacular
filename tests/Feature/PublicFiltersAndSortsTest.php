<?php

declare(strict_types=1);

use Bambamboole\Spectacular\QueryBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\QueryBuilder\AllowedFilter;
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

    Route::get('api/declared-public-users', fn () => response()->json(
        QueryBuilder::for(User::class)
            ->allowedFilters('name')
            ->allowedSorts('name')
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

it('allows public filters without an allowedFilters call', function (): void {
    $this->getJson('/api/public-users?filter[created_after]=2026-02-01')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'User 2');
});

it('allows public filters on a forwarded query execution', function (): void {
    $this->getJson('/api/public-users-list?filter[created_before]=2026-01-15')
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'User 1');
});

it('allows a non-scope public filter', function (): void {
    $this->getJson('/api/public-users?filter[email]=user2@example.com')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'User 2');
});

it('allows public sorts without an allowedSorts call', function (): void {
    $this->getJson('/api/public-users?sort=-created_at')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'User 3');

    $this->getJson('/api/public-users?sort=updated_at')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'User 1');
});

it('merges public declarations with explicit calls', function (): void {
    $this->getJson('/api/declared-public-users?filter[name]=User&filter[created_after]=2026-03-01&sort=-created_at')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'User 3');
});

it('keeps a re-declared public declaration single', function (): void {
    $this->getJson('/api/redeclared-public-users?filter[created_after]=2026-02-01&sort=-created_at')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'User 3');
});

it('rejects unknown filters and sorts', function (string $uri, string $query): void {
    $this->getJson("{$uri}?{$query}")->assertBadRequest();
})->with([
    'undeclared filter' => ['/api/public-users', 'filter[unknown]=x'],
    'undeclared sort' => ['/api/public-users', 'sort=unknown'],
    'declared filter' => ['/api/declared-public-users', 'filter[unknown]=x'],
    'declared sort' => ['/api/declared-public-users', 'sort=unknown'],
]);

it('allows public filters on a relation subject', function (): void {
    $this->getJson('/api/role-users?filter[created_after]=2026-02-01')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('leaves models without public declarations untouched', function (): void {
    $this->getJson('/api/public-roles?filter[name]=x')
        ->assertSuccessful();
});
