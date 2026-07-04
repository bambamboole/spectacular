<?php

declare(strict_types=1);

use Bambamboole\Spectacular\Doc\Adapters\OpenApiAdapter;
use Bambamboole\Spectacular\Doc\Model\HttpFacet;

/**
 * @return array<string, mixed>
 */
function workbenchOpenApi(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/workbench/fixtures/openapi.json'), true, flags: JSON_THROW_ON_ERROR);
}

it('adapts the workbench openapi document into operations grouped by tag', function (): void {
    $doc = (new OpenApiAdapter)->adapt(workbenchOpenApi());

    $ids = array_map(fn ($o) => $o->id, $doc->operations);
    expect($ids)->toContain('get-users', 'get-roles', 'get-categories')
        ->and(array_map(fn ($g) => $g->title, $doc->groups))->toContain('Users', 'Roles', 'Categories');

    $users = collect($doc->operations)->firstWhere('id', 'get-users');
    $facet = $users->facet;
    expect($facet)->toBeInstanceOf(HttpFacet::class);
    if ($facet instanceof HttpFacet) {
        expect($facet->method)->toBe('GET')
            ->and($facet->path)->toBe('/users');
    }

    $queryParams = collect($users->paramGroups)->firstWhere('location', 'query')->params;
    $paramNames = array_map(fn ($p) => $p->name, $queryParams);
    expect($paramNames)->toContain('page', 'per_page', 'filter[name]', 'sort', 'include', 'fields[users]');

    $response = $users->responses[0];
    expect($response->status)->toBe('200')
        ->and($response->mediaType)->toBe('application/vnd.api+json')
        ->and($response->schema['properties'])->toHaveKey('data');

    expect($doc->components['schemas'])->toHaveKeys(['UserResource', 'RoleResource', 'CategoryResource']);
});

it('resolves a response-level $ref against components.responses', function (): void {
    $doc = (new OpenApiAdapter)->adapt(workbenchOpenApi());

    $show = collect($doc->operations)->firstWhere('id', 'get-users-user');
    expect($show)->not->toBeNull();

    $ok = collect($show->responses)->firstWhere('status', '200');
    expect($ok)->not->toBeNull();

    $notFound = collect($show->responses)->firstWhere('status', '404');
    expect($notFound)->not->toBeNull()
        ->and($notFound->title)->toBe('Not found')
        ->and($notFound->schema)->not->toBe([])
        ->and($notFound->schema['properties'])->toHaveKey('message');
});

it('adapts an inline request body into a request contract', function (): void {
    $doc = (new OpenApiAdapter)->adapt(workbenchOpenApi());

    $createUser = collect($doc->operations)->firstWhere('id', 'post-users');
    expect($createUser)->not->toBeNull()
        ->and($createUser->requests)->toHaveCount(1);

    $request = $createUser->requests[0];
    expect($request->role)->toBe('request')
        ->and($request->status)->toBeNull()
        ->and($request->mediaType)->toBe('application/json')
        ->and($request->schema['properties'])->toHaveKeys(['name', 'email', 'roles'])
        ->and($request->schema['required'])->toContain('name', 'email');
});

it('resolves a request-body $ref against components.requestBodies', function (): void {
    $doc = (new OpenApiAdapter)->adapt([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Test', 'version' => '1.0.0'],
        'paths' => [
            '/users' => [
                'post' => [
                    'operationId' => 'storeUser',
                    'requestBody' => ['$ref' => '#/components/requestBodies/StoreUser'],
                    'responses' => [
                        '201' => ['description' => 'Created'],
                    ],
                ],
            ],
        ],
        'components' => [
            'requestBodies' => [
                'StoreUser' => [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => ['x' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $store = collect($doc->operations)->firstWhere('id', 'post-users');
    expect($store)->not->toBeNull()
        ->and($store->requests)->toHaveCount(1);

    expect($store->requests[0]->schema)->toBe([
        'type' => 'object',
        'properties' => ['x' => ['type' => 'string']],
    ]);
});

it('adds a multi-tagged operation to every one of its tags groups', function (): void {
    $doc = (new OpenApiAdapter)->adapt([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Test', 'version' => '1.0.0'],
        'paths' => [
            '/users/{user}/promote' => [
                'post' => [
                    'operationId' => 'promoteUser',
                    'tags' => ['Users', 'Admin'],
                    'responses' => [
                        '200' => ['description' => 'OK'],
                    ],
                ],
            ],
        ],
    ]);

    $usersGroup = collect($doc->groups)->firstWhere('title', 'Users');
    $adminGroup = collect($doc->groups)->firstWhere('title', 'Admin');

    expect($usersGroup)->not->toBeNull()
        ->and($adminGroup)->not->toBeNull()
        ->and($usersGroup->operationIds)->toContain('post-users-user-promote')
        ->and($adminGroup->operationIds)->toContain('post-users-user-promote');
});
