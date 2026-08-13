<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Attributes\SpecParameter;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
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

it('documents a generated query parameter when it is registered before scramble', function (): void {
    RouteFacade::get('api/ordered-users', OrderedUsersController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/ordered-users');
    $document = app(Generator::class)();

    $parameters = [];

    foreach (data_get($document, 'paths./ordered-users.get.parameters', []) as $parameter) {
        $parameters[$parameter['name']] = $parameter;
    }

    expect($parameters['filter[email]']['description'])->toBe('Exact email address to look for.')
        ->and($parameters['filter[email]']['x-tooltip'])->toBe('<a href="https://example.com/docs/filters">Filtering guide</a>');
});

final class OrderedArticlesController
{
    public function __invoke(StoreArticleData $data): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => $data->title]));
    }
}

final class OrderedUsersController
{
    #[SpecParameter(
        'filter[email]',
        description: 'Exact email address to look for.',
        tooltip: '<a href="https://example.com/docs/filters">Filtering guide</a>',
    )]
    public function __invoke(): AnonymousResourceCollection
    {
        return UserResource::collection(QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::exact('email'))
            ->get());
    }
}
