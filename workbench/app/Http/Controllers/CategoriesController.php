<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

class CategoriesController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $categories = QueryBuilder::for(Category::class)
            ->with('parent', 'children')
            ->paginate($request->integer('per_page', 15));

        $categories->appends($request->query());

        return CategoryResource::collection($categories);
    }
}
