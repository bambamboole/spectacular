<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

final class ShowUserController
{
    public function __invoke(User $user): UserResource
    {
        return new UserResource($user);
    }
}
