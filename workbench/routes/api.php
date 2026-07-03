<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\CategoriesController;
use Workbench\App\Http\Controllers\RolesController;
use Workbench\App\Http\Controllers\ShowUserController;
use Workbench\App\Http\Controllers\StoreUserController;
use Workbench\App\Http\Controllers\UsersController;

Route::get('api/roles', RolesController::class)->name('api.roles.index');
Route::get('api/users', UsersController::class)->name('api.users.index');
Route::post('api/users', StoreUserController::class)->name('api.users.store');
Route::get('api/users/{user}', ShowUserController::class)->name('api.users.show');
Route::get('api/categories', CategoriesController::class)->name('api.categories.index');
