<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Bambamboole\Spectacular\PaginationMode;
use Bambamboole\Spectacular\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

class UsersController
{
    public function __invoke(): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                'name',
                AllowedFilter::exact('email'),
            )
            ->allowedSorts('name', 'created_at')
            ->allowedIncludes('roles')
            ->allowedFields('id', 'name', 'email', 'roles.id', 'roles.name')
            ->apiPaginate(modes: [
                PaginationMode::Default,
                PaginationMode::Simple,
                PaginationMode::Cursor,
            ]);

        return UserResource::collection($users);
    }
}
