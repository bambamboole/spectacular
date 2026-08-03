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
    /**
     * List product categories.
     *
     * Browse the category hierarchy used to organize products. Each category includes its parent and direct children so clients can build navigation without follow-up requests.
     *
     * Results are paginated so clients can request a predictable slice of the collection. Use `per_page` and `page` to move through the result set.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $categories = QueryBuilder::for(Category::class)
            ->with('parent', 'children')
            ->paginate($request->integer('per_page', 15));

        $categories->appends($request->query());

        return CategoryResource::collection($categories);
    }
}
