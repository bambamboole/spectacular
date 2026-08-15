<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Attributes\SpecEndpoint;
use Bambamboole\Spectacular\Attributes\SpecParameter;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\LaravelData\Data;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('describes a payload field from its attribute', function (): void {
    $name = documentedPayloadProperties()['name'];

    expect($name['description'])->toBe('Display name of the author.')
        ->and($name['x-tooltip'])->toBe('<a href="https://example.com/docs">Naming rules</a>');
});

it('keeps describing a payload field from its docblock', function (): void {
    $summary = documentedPayloadProperties()['summary'];

    expect($summary['description'])->toBe('Teaser shown in listings.')
        ->and($summary)->not->toHaveKey('x-tooltip');
});

it('prefers the attribute description over the docblock of the same property', function (): void {
    expect(documentedPayloadProperties()['title']['description'])->toBe('Headline of the article.');
});

it('adds a tooltip to a payload field described by its docblock', function (): void {
    $slug = documentedPayloadProperties()['slug'];

    expect($slug['description'])->toBe('URL segment of the article.')
        ->and($slug['x-tooltip'])->toBe('Generated from the title when omitted.');
});

it('documents a query parameter the query builder extension generated', function (): void {
    $filter = documentedAttributedParameters()['filter[email]'];

    expect($filter['description'])->toBe('Exact email address to look for.')
        ->and($filter['x-tooltip'])->toBe('<a href="https://example.com/docs/filters">Filtering guide</a>')
        ->and($filter['schema'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
});

it('documents a query parameter the pagination extension generated', function (): void {
    expect(documentedAttributedParameters()['per_page']['schema']['default'])->toBe(25);
});

it('documents a path parameter', function (): void {
    RouteFacade::get('api/attributed-users/{user}', AttributedUserController::class);

    $user = documentedParametersForUri('api/attributed-users/{user}')['user'];

    expect($user['description'])->toBe('Identifier of the user to load.')
        ->and($user['x-tooltip'])->toBe('<a href="https://example.com/docs/users">User guide</a>');
});

it('adds a tooltip to the operation', function (): void {
    RouteFacade::get('api/attributed-users', AttributedUsersController::class);

    expect(documentedOperationForUri('api/attributed-users')['x-tooltip'])
        ->toBe('<a href="https://example.com/docs/users">User guide</a>');
});

it('leaves an endpoint without attributes untouched', function (): void {
    RouteFacade::get('api/plain-users/{user}', PlainUserController::class);

    $operation = documentedOperationForUri('api/plain-users/{user}');

    expect($operation)->not->toHaveKey('x-tooltip')
        ->and($operation['parameters'][0])->not->toHaveKey('x-tooltip');
});

/**
 * @return array<string, array<string, mixed>>
 */
function documentedPayloadProperties(): array
{
    RouteFacade::post('api/attributed-articles', AttributedArticlesController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/attributed-articles');

    $properties = data_get(app(Generator::class)(), 'components.schemas.AttributedArticleData.properties');

    return is_array($properties) ? $properties : [];
}

/**
 * @return array<string, array<string, mixed>>
 */
function documentedAttributedParameters(): array
{
    RouteFacade::get('api/attributed-users', AttributedUsersController::class);

    return documentedParametersForUri('api/attributed-users');
}

/**
 * @return array<string, array<string, mixed>>
 */
function documentedParametersForUri(string $uri): array
{
    $parameters = [];

    foreach (documentedOperationForUri($uri)['parameters'] ?? [] as $parameter) {
        if (is_array($parameter) && is_string($parameter['name'] ?? null)) {
            $parameters[$parameter['name']] = $parameter;
        }
    }

    return $parameters;
}

/**
 * @return array<string, mixed>
 */
function documentedOperationForUri(string $uri, string $method = 'get'): array
{
    Scramble::routes(fn (Route $route): bool => $route->uri() === $uri);

    $path = preg_replace('/^api/', '', $uri);
    $operation = data_get(app(Generator::class)(), "paths.{$path}.{$method}");

    return is_array($operation) ? $operation : [];
}

final class AttributedArticleData extends Data
{
    public function __construct(
        /** A docblock the attribute overrules. */
        #[SpecProperty(description: 'Headline of the article.')]
        public string $title,
        #[SpecProperty(
            description: 'Display name of the author.',
            tooltip: '<a href="https://example.com/docs">Naming rules</a>',
        )]
        public string $name = '',
        /** URL segment of the article. */
        #[SpecProperty(tooltip: 'Generated from the title when omitted.')]
        public ?string $slug = null,
        /** Teaser shown in listings. */
        public ?string $summary = null,
    ) {}
}

final class AttributedArticlesController
{
    public function __invoke(AttributedArticleData $data): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => $data->title]));
    }
}

final class AttributedUsersController
{
    #[SpecEndpoint(tooltip: '<a href="https://example.com/docs/users">User guide</a>')]
    #[SpecParameter(
        'filter[email]',
        description: 'Exact email address to look for.',
        tooltip: '<a href="https://example.com/docs/filters">Filtering guide</a>',
    )]
    #[SpecParameter('per_page', default: 25)]
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::exact('email'))
            ->paginate($request->integer('per_page', 15)));
    }
}

final class AttributedUserController
{
    #[SpecParameter(
        'user',
        description: 'Identifier of the user to load.',
        tooltip: '<a href="https://example.com/docs/users">User guide</a>',
    )]
    public function __invoke(User $user): UserResource
    {
        return new UserResource($user);
    }
}

final class PlainUserController
{
    public function __invoke(User $user): UserResource
    {
        return new UserResource($user);
    }
}
