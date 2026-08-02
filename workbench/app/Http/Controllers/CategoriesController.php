<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Dedoc\Scramble\Attributes\Header;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

class CategoriesController
{
    #[Header(name: 'X-Total-Count', description: 'Total number of categories.', type: 'int', status: 200)]
    public function __invoke(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::with('parent', 'children')->get());
    }
}
