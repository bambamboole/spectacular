<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

final class StoreUserController
{
    public function __invoke(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'roles' => ['array'],
            'roles.*' => ['integer'],
        ]);

        return new UserResource(User::create($validated));
    }
}
