<?php
declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

class RoleResource extends JsonApiResource
{
    /** @var list<string> */
    public array $attributes = [
        'name',
    ];

    public function toType(Request $request): string
    {
        return 'roles';
    }
}
