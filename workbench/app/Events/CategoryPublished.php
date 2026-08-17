<?php
declare(strict_types=1);

namespace Workbench\App\Events;

use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

#[WebhookEvent(name: 'category.published', title: 'Category Published', summary: 'Sent when a workbench category goes live.', tags: ['catalog'])]
final class CategoryPublished
{
    public function __construct(
        public Category $category,
    ) {}

    /**
     * @return array{category: CategoryResource, publishedBy: string}
     */
    public function webhookPayload(): array
    {
        return [
            'category' => new CategoryResource($this->category),
            'publishedBy' => 'workbench',
        ];
    }
}
