<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Lattice\LatticeServiceProvider;
use Workbench\App\Pages\ApiReferencePage;
use Workbench\App\Providers\WorkbenchServiceProvider;

it('executes API requests from the workbench origin', function () {
    app()->register(InertiaServiceProvider::class);
    app()->register(LatticeServiceProvider::class);
    app()->register(WorkbenchServiceProvider::class);

    Route::get('/docs-browser', [ApiReferencePage::class, 'render']);
    Route::get('/api/categories', fn () => response()->json(['data' => []]));

    visit('/docs-browser')
        ->assertSee('/api')
        ->click('button:has-text("Execute")')
        ->assertSee('200 OK')
        ->assertNoJavaScriptErrors();
});

it('selects and sends an available pagination mode', function () {
    app()->register(InertiaServiceProvider::class);
    app()->register(LatticeServiceProvider::class);
    app()->register(WorkbenchServiceProvider::class);

    Route::get('/docs-browser', [ApiReferencePage::class, 'render']);
    Route::get('/api/users', fn (Request $request) => response()->json([
        'received_pagination_mode' => $request->header('x-pagination'),
    ]));

    $paginationSelector = 'select[data-field-key="header:x-pagination"]';
    $pageSelector = 'input[data-field-key="query:page"]';
    $cursorSelector = 'input[data-field-key="query:cursor"]';
    $perPageSelector = 'input[data-field-key="query:per_page"]';

    visit('/docs-browser')
        ->click('button[aria-label="users.index"]')
        ->assertSelected($paginationSelector, 'default')
        ->assertPresent($pageSelector)
        ->assertMissing($cursorSelector)
        ->assertPresent($perPageSelector)
        ->select($paginationSelector, 'cursor')
        ->assertMissing($pageSelector)
        ->assertPresent($cursorSelector)
        ->assertPresent($perPageSelector)
        ->assertSee('x-pagination: cursor')
        ->click('button:has-text("Execute")')
        ->assertSee('received_pagination_mode')
        ->assertSee('cursor')
        ->assertNoJavaScriptErrors();
});
