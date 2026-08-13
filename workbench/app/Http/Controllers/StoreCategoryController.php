<?php
declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Bambamboole\Spectacular\Attributes\SpecEndpoint;
use Workbench\App\Data\StoreCategoryData;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

final class StoreCategoryController
{
    #[SpecEndpoint(tooltip: 'Creating a category requires the <code>categories:write</code> scope.')]
    public function __invoke(StoreCategoryData $data): CategoryResource
    {
        return new CategoryResource(Category::create([
            'name' => $data->name,
            'status' => $data->status,
            'is_visible' => $data->isVisible,
            'parent_id' => $data->parentId,
        ]));
    }
}
