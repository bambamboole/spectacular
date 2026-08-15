<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Bambamboole\Spectacular\Attributes\SpecParameter;
use Bambamboole\Spectacular\QueryBuilder;
use Dedoc\Scramble\Attributes\PathParameter;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

final class ShowUserController
{
    #[PathParameter('user', example: 1)]
    #[SpecParameter(
        'user',
        description: 'Identifier of the user to load.',
        tooltip: 'Accepts the numeric id only. See the <a href="https://example.com/docs/users">user guide</a>.',
    )]
    public function __invoke(User $user): UserResource
    {
        return new UserResource(QueryBuilder::for(User::class)->apiFindOrFail($user->getKey()));
    }
}
