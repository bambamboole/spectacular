<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Workbench\App\Data\StoreArticleData;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('applies its transformers when it is registered before scramble', function (): void {
    RouteFacade::post('api/ordered-articles', OrderedArticlesController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/ordered-articles');
    $document = app(Generator::class)();

    $operation = data_get($document, 'paths./ordered-articles.post');

    expect(array_map(strval(...), array_keys($operation['responses'] ?? [])))->toContain('422')
        ->and(data_get($document, 'components.schemas.StoreArticleData.properties.title.description'))
        ->toBe('Headline of the article.')
        ->and(data_get($document, 'components.schemas.StoreArticleData.required'))
        ->toBe(['title']);
});

final class OrderedArticlesController
{
    public function __invoke(StoreArticleData $data): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => $data->title]));
    }
}
