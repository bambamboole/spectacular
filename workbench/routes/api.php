<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Workbench\App\Http\Controllers\RolesController;
use Workbench\App\Http\Controllers\UsersController;

Route::get('api/roles', RolesController::class)->name('api.roles.index');
Route::get('api/users', UsersController::class)->name('api.users.index');
