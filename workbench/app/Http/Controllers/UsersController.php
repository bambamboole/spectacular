<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

class UsersController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('email'),
            )
            ->allowedSorts('name', 'created_at')
            ->allowedIncludes('roles')
            ->allowedFields('id', 'name', 'email', 'roles.id', 'roles.name')
            ->paginate($request->integer('per_page', 15));

        $users->appends($request->query());

        return UserResource::collection($users);
    }
}
