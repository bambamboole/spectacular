<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

class CategoriesController
{
    public function __invoke(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::with('parent', 'children')->get());
    }
}
