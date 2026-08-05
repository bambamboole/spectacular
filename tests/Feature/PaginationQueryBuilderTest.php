<?php

declare(strict_types=1);

use Bambamboole\Spectacular\PaginationMode;
use Bambamboole\Spectacular\QueryBuilder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    foreach (range(1, 16) as $number) {
        User::query()->create([
            'name' => "User {$number}",
            'email' => "user{$number}@example.com",
            'password' => 'password',
        ]);
    }

    Route::get('api/pagination-users', fn () => response()->json(
        QueryBuilder::for(User::class)->apiPaginate(),
    ));

    Route::get('api/multiple-pagination-users', fn () => response()->json(
        QueryBuilder::for(User::class)
            ->allowedFilters('name')
            ->apiPaginate([
                PaginationMode::Default,
                PaginationMode::Simple,
                PaginationMode::Cursor,
            ]),
    ));

    Route::get('api/cursor-first-pagination-users', fn () => response()->json(
        QueryBuilder::for(User::class)->apiPaginate([
            PaginationMode::Cursor,
            PaginationMode::Default,
        ]),
    ));

    Route::get('api/clamped-pagination-users', function () {
        $query = User::query();
        $query->getModel()->setPerPage(10);

        return response()->json(QueryBuilder::for($query)->apiPaginate(max: 3));
    });
});

it('uses default pagination and the model page size', function (): void {
    $this->getJson('/api/pagination-users')
        ->assertSuccessful()
        ->assertJsonPath('current_page', 1)
        ->assertJsonPath('per_page', 15)
        ->assertJsonPath('total', 16);
});

it('selects each declared pagination mode', function (string $mode, string $present, string $missing): void {
    $this->withHeader('x-pagination', $mode)
        ->getJson('/api/multiple-pagination-users')
        ->assertSuccessful()
        ->assertJsonStructure([$present])
        ->assertJsonMissingPath($missing);
})->with([
    'simple' => ['simple', 'current_page', 'total'],
    'cursor' => ['cursor', 'next_cursor', 'current_page'],
]);

it('uses the first declared pagination mode when the header is omitted', function (): void {
    $this->getJson('/api/cursor-first-pagination-users')
        ->assertSuccessful()
        ->assertJsonStructure(['next_cursor'])
        ->assertJsonMissingPath('current_page');
});

it('rejects unknown and unavailable pagination modes', function (string $uri, string $mode): void {
    $this->withHeader('x-pagination', $mode)
        ->getJson($uri)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('x-pagination');
})->with([
    'unknown' => ['/api/multiple-pagination-users', 'unknown'],
    'unavailable' => ['/api/pagination-users', 'cursor'],
]);

it('uses the model page size without exceeding the maximum', function (): void {
    $this->getJson('/api/clamped-pagination-users')
        ->assertSuccessful()
        ->assertJsonPath('per_page', 3);
});

it('clamps supplied page sizes', function (int $requested, int $expected): void {
    $this->getJson("/api/clamped-pagination-users?per_page={$requested}")
        ->assertSuccessful()
        ->assertJsonPath('per_page', $expected);
})->with([
    'below minimum' => [0, 1],
    'within range' => [2, 2],
    'above maximum' => [101, 3],
]);

it('rejects a non-integer page size', function (): void {
    $this->getJson('/api/clamped-pagination-users?per_page=many')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

it('retains query parameters in pagination links', function (): void {
    $this->getJson('/api/multiple-pagination-users?per_page=1&filter[name]=User')
        ->assertSuccessful()
        ->assertJsonPath('next_page_url', fn (string $url): bool => str_contains($url, 'per_page=1')
            && str_contains($url, 'filter%5Bname%5D=User'));
});
