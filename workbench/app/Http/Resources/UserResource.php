<?php
declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class UserResource extends JsonApiResource
{
    /** @var list<string> */
    public array $attributes = [
        'name',
        'email',
    ];

    /** @var array<string, class-string<JsonApiResource>> */
    public array $relationships = [
        'roles' => RoleResource::class,
    ];

    public function toType(Request $request): string
    {
        return 'users';
    }
}
