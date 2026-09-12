<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\LaravelData\CursorPaginatedDataCollection;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Workbench\App\Data\StoreCategoryData;
use Workbench\App\Http\Resources\CategoryResource;
use Workbench\App\Models\Category;

it('documents a paginated data collection as the paginator envelope', function (): void {
    $response = generatedDataResponse('data-paginated');

    expect($response['description'])->toBe('Paginated set of `StoreCategoryData`')
        ->and($response['schema']['required'])->toBe(['data', 'links', 'meta'])
        ->and($response['schema']['properties']['data'])
        ->toBe(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StoreCategoryData']])
        ->and($response['schema']['properties']['links'])->toBe([
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => ['string', 'null']],
                    'label' => ['type' => 'string'],
                    'active' => ['type' => 'boolean'],
                ],
                'required' => ['url', 'label', 'active'],
            ],
        ])
        ->and($response['schema']['properties']['meta']['required'])->toBe([
            'current_page', 'first_page_url', 'from', 'last_page', 'last_page_url', 'next_page_url',
            'path', 'per_page', 'prev_page_url', 'to', 'total',
        ])
        ->and(array_keys($response['schema']['properties']['meta']['properties']))
        ->toBe($response['schema']['properties']['meta']['required']);
});

it('resolves the collected class from the collect call without a docblock', function (): void {
    $response = generatedDataResponse('data-inferred');

    expect($response['description'])->toBe('Paginated set of `StoreCategoryData`')
        ->and($response['schema']['properties']['data']['items'])
        ->toBe(['$ref' => '#/components/schemas/StoreCategoryData']);
});

it('documents a data collection as an array of the collected class', function (): void {
    $response = generatedDataResponse('data-collection');

    expect($response['description'])->toBe('Array of `StoreCategoryData`')
        ->and($response['schema'])
        ->toBe(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StoreCategoryData']]);
});

it('documents a cursor paginated data collection with the cursor meta', function (): void {
    $response = generatedDataResponse('data-cursor');

    expect($response['description'])->toBe('Paginated set of `StoreCategoryData`')
        ->and($response['schema']['required'])->toBe(['data', 'links', 'meta'])
        ->and($response['schema']['properties']['links'])->toBe(['type' => 'array', 'items' => []])
        ->and($response['schema']['properties']['meta']['required'])->toBe([
            'path', 'per_page', 'next_cursor', 'next_page_url', 'prev_cursor', 'prev_page_url',
        ]);
});

it('documents a single data object without a wrapper when wrapping is off', function (): void {
    expect(generatedDataResponse('data-single')['schema'])
        ->toBe(['$ref' => '#/components/schemas/StoreCategoryData']);
});

it('wraps a single data object under the configured wrap key', function (): void {
    config()->set('data.wrap', 'data');

    expect(generatedDataResponse('data-single')['schema'])->toBe([
        'type' => 'object',
        'properties' => ['data' => ['$ref' => '#/components/schemas/StoreCategoryData']],
        'required' => ['data'],
    ]);
});

it('nests a data collection under the configured wrap key', function (): void {
    config()->set('data.wrap', 'items');

    expect(generatedDataResponse('data-collection')['schema'])->toBe([
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StoreCategoryData']],
        ],
        'required' => ['items'],
    ]);
});

it('collects a paginated data collection under the configured wrap key', function (): void {
    config()->set('data.wrap', 'items');

    $properties = generatedDataResponse('data-paginated')['schema']['properties'];

    expect(array_keys($properties))->toBe(['items', 'links', 'meta'])
        ->and($properties['items']['items'])->toBe(['$ref' => '#/components/schemas/StoreCategoryData']);
});

it('leaves a json resource collection alone, wrap key included', function (): void {
    config()->set('data.wrap', 'items');

    expect(generatedDataResponse('data-resources')['schema'])->toBe([
        'type' => 'object',
        'properties' => [
            'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CategoryResource']],
        ],
        'required' => ['data'],
    ]);
});

/**
 * @return array{description: string, schema: array<string, mixed>}
 */
function generatedDataResponse(string $path): array
{
    RouteFacade::get('api/data-paginated', [DataResponsesController::class, 'paginated']);
    RouteFacade::get('api/data-collection', [DataResponsesController::class, 'collection']);
    RouteFacade::get('api/data-cursor', [DataResponsesController::class, 'cursor']);
    RouteFacade::get('api/data-single', [DataResponsesController::class, 'single']);
    RouteFacade::get('api/data-resources', [DataResponsesController::class, 'resources']);
    RouteFacade::get(
        'api/data-inferred',
        fn () => StoreCategoryData::collect(Category::query()->paginate(), PaginatedDataCollection::class),
    );

    Scramble::routes(fn (Route $route): bool => $route->uri() === "api/{$path}");

    /** @var array<string, mixed> $document */
    $document = json_decode((string) json_encode(app(Generator::class)()), true);

    return [
        'description' => (string) data_get($document, "paths./{$path}.get.responses.200.description", ''),
        'schema' => data_get($document, "paths./{$path}.get.responses.200.content.application/json.schema", []),
    ];
}

final class DataResponsesController
{
    /** @return PaginatedDataCollection<array-key, StoreCategoryData> */
    public function paginated(): PaginatedDataCollection
    {
        return StoreCategoryData::collect(Category::query()->paginate(), PaginatedDataCollection::class);
    }

    /** @return DataCollection<array-key, StoreCategoryData> */
    public function collection(): DataCollection
    {
        return StoreCategoryData::collect(Category::all(), DataCollection::class);
    }

    /** @return CursorPaginatedDataCollection<array-key, StoreCategoryData> */
    public function cursor(): CursorPaginatedDataCollection
    {
        /** @var CursorPaginator<int, Category> $paginator */
        $paginator = Category::query()->cursorPaginate();

        return StoreCategoryData::collect($paginator, CursorPaginatedDataCollection::class);
    }

    public function single(): StoreCategoryData
    {
        return StoreCategoryData::from(Category::query()->firstOrNew());
    }

    /** @return AnonymousResourceCollection<array-key, CategoryResource> */
    public function resources(): AnonymousResourceCollection
    {
        return CategoryResource::collection(Category::query()->paginate());
    }
}
