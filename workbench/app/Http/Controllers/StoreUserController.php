<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

final class StoreUserController
{
    #[HeaderParameter(
        name: 'X-Debug-Context',
        description: 'Optional context echoed in request diagnostics.',
        required: false,
        type: 'string',
        example: 'docs',
    )]
    public function __invoke(Request $request): UserResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'roles' => ['array'],
            'roles.*' => ['integer'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        return new UserResource(User::create($validated));
    }
}
