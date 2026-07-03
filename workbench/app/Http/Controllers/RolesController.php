<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Http\Resources\RoleResource;
use Workbench\App\Models\Role;

class RolesController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->defaultSort('id')
            ->cursorPaginate($request->integer('per_page', 15));

        $roles->appends($request->query());

        return RoleResource::collection($roles);
    }
}
