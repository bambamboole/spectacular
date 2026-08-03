<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Lattice\Lattice\LatticeServiceProvider;
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
