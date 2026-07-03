<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route as RouteFacade;
use Spatie\QueryBuilder\QueryBuilder;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('documents spatie query builder parameters from the route action', function (): void {
    $parameters = generatedUsersOperationParameters();

    expect($parameters)
        ->toHaveKeys([
            'filter[name]',
            'filter[email]',
            'sort',
            'include',
            'fields[users]',
            'fields[roles]',
        ])
        ->and($parameters['filter[name]']['schema'])
        ->toBe(['type' => 'string'])
        ->and($parameters['filter[name]']['description'])
        ->toBe('Filter by `name`.')
        ->and($parameters['filter[email]']['schema'])
        ->toBe(['type' => 'string'])
        ->and($parameters['filter[email]']['description'])
        ->toBe('Filter by `email`.')
        ->and($parameters['sort'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'Available sorts are `name`, `created_at`. You can sort by multiple options by separating them with a comma. To sort in descending order, use `-` sign in front of the sort, for example: `-name`.',
            'style' => 'form',
            'explode' => false,
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['name', '-name', 'created_at', '-created_at'],
                ],
            ],
        ])
        ->and($parameters['include'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'Available includes are `roles`, `rolesCount`, `rolesExists`. You can include multiple options by separating them with a comma.',
            'style' => 'form',
            'explode' => false,
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['roles', 'rolesCount', 'rolesExists'],
                ],
            ],
        ])
        ->and($parameters['fields[users]'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'Available fields are `id`, `name`, `email`. You can include multiple options by separating them with a comma.',
            'style' => 'form',
            'explode' => false,
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['id', 'name', 'email'],
                ],
            ],
        ])
        ->and($parameters['fields[roles]'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'Available fields are `id`, `name`. You can include multiple options by separating them with a comma.',
            'style' => 'form',
            'explode' => false,
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['id', 'name'],
                ],
            ],
        ]);
});

it('matches the workbench OpenAPI fixture', function (): void {
    $fixture = workbenchOpenApiFixturePath();

    expect($fixture)->toBeFile()
        ->and(generatedWorkbenchOpenApiJson())->toBe(file_get_contents($fixture));
});

it('writes the generated OpenAPI document to stdout or to a file path', function (): void {
    $path = sys_get_temp_dir().'/spectacular-openapi-command.json';

    if (file_exists($path)) {
        unlink($path);
    }

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/users', 'api/roles', 'api/categories'], true));

    expect(Artisan::call('spectacular:openapi'))->toBe(0)
        ->and(Artisan::output())->toContain('"openapi": "3.1.0"');

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/users', 'api/roles', 'api/categories'], true));

    expect(Artisan::call('spectacular:openapi', ['--path' => $path]))->toBe(0)
        ->and(json_decode((string) file_get_contents($path), true))
        ->toBe(generatedWorkbenchOpenApiDocument());

    unlink($path);
});

it('documents the json api resource response from the workbench endpoint', function (): void {
    $document = generatedUsersOpenApiDocument();
    $operation = generatedUsersOperation();
    $schema = $operation['responses']['200']['content']['application/vnd.api+json']['schema'];
    $userResource = $document['components']['schemas']['UserResource'];
    $roleResource = $document['components']['schemas']['RoleResource'];

    expect($operation['responses']['200']['content'])
        ->toHaveKey('application/vnd.api+json')
        ->and($schema['type'])->toBe('object')
        ->and($schema['required'])->toBe(['data', 'links', 'meta'])
        ->and($schema['properties'])->toHaveKeys(['data', 'links', 'meta', 'included'])
        ->and($schema['properties']['data'])->toMatchArray([
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/UserResource',
            ],
        ])
        ->and($schema['properties']['links'])->toMatchArray([
            'type' => 'object',
        ])
        ->and($schema['properties']['meta'])->toMatchArray([
            'type' => 'object',
        ])
        ->and($schema['properties']['included'])->toMatchArray([
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/RoleResource',
            ],
        ])
        ->and($userResource['type'])->toBe('object')
        ->and($userResource['required'])->toBe(['id', 'type'])
        ->and($userResource['properties']['id'])->toBe(['type' => 'string'])
        ->and($userResource['properties']['type'])->toBe(['type' => 'string', 'const' => 'users'])
        ->and($userResource['properties']['attributes']['properties'])
        ->toMatchArray([
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ])
        ->and($userResource['properties']['relationships']['properties']['roles']['properties']['data']['properties']['type'])
        ->toBe(['type' => 'string', 'const' => 'roles'])
        ->and($roleResource['type'])->toBe('object')
        ->and($roleResource['required'])->toBe(['id', 'type'])
        ->and($roleResource['properties']['id'])->toBe(['type' => 'string'])
        ->and($roleResource['properties']['type'])->toBe(['type' => 'string', 'const' => 'roles'])
        ->and($roleResource['properties']['attributes']['properties'])
        ->toMatchArray([
            'name' => ['type' => 'string'],
        ]);
});

it('documents pagination parameters and the paginated json api resource response', function (): void {
    $operation = generatedUsersOperation();
    $parameters = generatedUsersOperationParameters();
    $schema = $operation['responses']['200']['content']['application/vnd.api+json']['schema'];

    expect($parameters)
        ->toHaveKeys(['page', 'per_page'])
        ->and($parameters['page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The page number to retrieve.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
            ],
        ])
        ->and($parameters['per_page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The number of items to retrieve per page.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 15,
            ],
        ])
        ->and($schema['properties'])
        ->toHaveKeys(['data', 'links', 'meta'])
        ->and($schema['required'])
        ->toBe(['data', 'links', 'meta']);
});

it('documents the cursor paginated roles workbench endpoint', function (): void {
    $operation = generatedRolesOperation();
    $parameters = generatedRolesOperationParameters();
    $schema = $operation['responses']['200']['content']['application/vnd.api+json']['schema'] ?? [];

    expect(array_key_exists('page', $parameters))->toBeFalse();

    expect($parameters)
        ->toHaveKeys(['cursor', 'per_page'])
        ->and($parameters['cursor'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The cursor to start pagination from.',
            'schema' => [
                'type' => 'string',
            ],
        ])
        ->and($parameters['per_page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The number of items to retrieve per page.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 15,
            ],
        ])
        ->and($schema['required'])
        ->toBe(['data', 'links', 'meta'])
        ->and($schema['properties']['data'])
        ->toMatchArray([
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/RoleResource',
            ],
        ]);
});

it('documents simple pagination parameters from simplePaginate', function (): void {
    RouteFacade::get('api/simple-users', SimplePaginatedUsersController::class)->name('api.simple-users.index');

    $parameters = generatedOperationParametersForUri('api/simple-users');

    expect($parameters)
        ->toHaveKeys(['page', 'per_page'])
        ->and($parameters['page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The page number to retrieve.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
            ],
        ])
        ->and($parameters['per_page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The number of items to retrieve per page.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 15,
            ],
        ]);
});

it('documents cursor pagination parameters from cursorPaginate', function (): void {
    RouteFacade::get('api/cursor-users', CursorPaginatedUsersController::class)->name('api.cursor-users.index');

    $parameters = generatedOperationParametersForUri('api/cursor-users');

    expect(array_key_exists('page', $parameters))->toBeFalse();

    expect($parameters)
        ->toHaveKeys(['cursor', 'per_page'])
        ->and($parameters['cursor'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The cursor to start pagination from.',
            'schema' => [
                'type' => 'string',
            ],
        ])
        ->and($parameters['per_page'])
        ->toMatchArray([
            'in' => 'query',
            'description' => 'The number of items to retrieve per page.',
            'schema' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 15,
            ],
        ]);
});

/**
 * @return array<string, array<string, mixed>>
 */
function generatedUsersOperationParameters(): array
{
    return generatedOperationParametersForUri('api/users');
}

/**
 * @return array<string, array<string, mixed>>
 */
function generatedRolesOperationParameters(): array
{
    return generatedOperationParametersForUri('api/roles');
}

/**
 * @return array<string, array<string, mixed>>
 */
function generatedOperationParametersForUri(string $uri): array
{
    $operation = generatedOperationForUri($uri);
    $operationParameters = $operation['parameters'] ?? [];

    if (! is_array($operationParameters)) {
        return [];
    }

    $parameters = [];

    foreach ($operationParameters as $parameter) {
        if (is_array($parameter) && is_string($parameter['name'] ?? null)) {
            $parameters[$parameter['name']] = $parameter;
        }
    }

    return $parameters;
}

/**
 * @return array<string, mixed>
 */
function generatedUsersOperation(): array
{
    return generatedOperationForUri('api/users');
}

/**
 * @return array<string, mixed>
 */
function generatedRolesOperation(): array
{
    return generatedOperationForUri('api/roles');
}

/**
 * @return array<string, mixed>
 */
function generatedOperationForUri(string $uri): array
{
    $paths = generatedOpenApiDocumentForUri($uri)['paths'] ?? [];

    if (! is_array($paths)) {
        return [];
    }

    $path = $paths['/'.preg_replace('/^api\//', '', $uri)] ?? [];

    if (! is_array($path)) {
        return [];
    }

    $operation = $path['get'] ?? [];

    if (! is_array($operation)) {
        return [];
    }

    return $operation;
}

/**
 * @return array<string, mixed>
 */
function generatedUsersOpenApiDocument(): array
{
    return generatedOpenApiDocumentForUri('api/users');
}

/**
 * @return array<string, mixed>
 */
function generatedWorkbenchOpenApiDocument(): array
{
    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/users', 'api/roles', 'api/categories'], true));

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

/**
 * @return array<string, mixed>
 */
function generatedOpenApiDocumentForUri(string $uri): array
{
    Scramble::routes(fn (Route $route): bool => $route->uri() === $uri);

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

function generatedWorkbenchOpenApiJson(): string
{
    return json_encode(generatedWorkbenchOpenApiDocument(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
}

function workbenchOpenApiFixturePath(): string
{
    return dirname(__DIR__, 2).'/workbench/fixtures/openapi.json';
}

final class SimplePaginatedUsersController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(QueryBuilder::for(User::class)
            ->simplePaginate($request->integer('per_page', 15)));
    }
}

final class CursorPaginatedUsersController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(QueryBuilder::for(User::class)
            ->defaultSort('id')
            ->cursorPaginate($request->integer('per_page', 15)));
    }
}
