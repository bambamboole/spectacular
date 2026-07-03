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
