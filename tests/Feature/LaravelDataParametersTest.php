<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Workbench\App\Data\StoreArticleData;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('documents the request body of an action taking a data object', function (): void {
    $schema = generatedArticleRequestSchema();

    expect(array_keys($schema['properties'] ?? []))
        ->toBe(['title', 'author', 'summary', 'is_published', 'translations', 'sections']);
});

it('describes payload properties from their promoted property docblocks', function (): void {
    $properties = generatedArticleRequestSchema()['properties'];

    expect($properties['title']['description'])->toBe('Headline of the article.')
        ->and($properties['summary']['description'])->toBe('Teaser shown in listings.')
        ->and($properties['is_published']['description'])->toBe('Whether the article is publicly visible. Defaults to false.');
});

it('requires only the fields a client has to send', function (): void {
    expect(generatedArticleRequestSchema()['required'] ?? [])->toBe(['title']);
});

it('keeps a mandatory field inside an optional object from making that object mandatory', function (): void {
    expect(generatedArticleSchemas()['ArticleAuthorData']['required'] ?? [])->toBe(['name']);
});

it('references a data object nesting itself instead of degrading to strings', function (): void {
    $schemas = generatedArticleSchemas();

    expect($schemas['StoreArticleData']['properties']['translations']['items'] ?? null)
        ->toBe(['$ref' => '#/components/schemas/StoreArticleData'])
        ->and($schemas['ArticleSectionData']['properties']['children']['items'] ?? null)
        ->toBe(['$ref' => '#/components/schemas/ArticleSectionData']);
});

it('does not require defaulted properties of a nested data collection item', function (): void {
    $schemas = generatedArticleSchemas();

    expect($schemas['StoreArticleData']['properties']['sections']['items'] ?? null)
        ->toBe(['$ref' => '#/components/schemas/ArticleSectionData'])
        ->and($schemas['ArticleSectionData']['required'] ?? [])->toBe(['heading']);
});

it('keeps the null of a nullable nested data object', function (): void {
    $author = generatedArticleSchemas()['StoreArticleData']['properties']['author'];

    expect($author['anyOf'] ?? null)->not->toBeNull()
        ->and($author['anyOf'][0]['$ref'] ?? null)->toBe('#/components/schemas/ArticleAuthorData')
        ->and($author['anyOf'][1]['type'] ?? null)->toBe('null');
});

it('leaves actions without a data object to scramble', function (): void {
    RouteFacade::post('api/plain-articles', PlainArticlesController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/plain-articles');
    $document = app(Generator::class)();

    expect(array_keys(data_get($document, 'paths./plain-articles.post.requestBody.content.application/json.schema.properties', [])))
        ->toBe(['title']);
});

/**
 * @return array<string, mixed>
 */
function generatedArticleRequestSchema(): array
{
    return generatedArticleSchemas()['StoreArticleData'] ?? [];
}

/**
 * @return array<string, array<string, mixed>>
 */
function generatedArticleSchemas(): array
{
    RouteFacade::post('api/articles', StoreArticleController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/articles');
    $document = app(Generator::class)();

    $schemas = data_get($document, 'components.schemas');

    return is_array($schemas) ? $schemas : [];
}

final class StoreArticleController
{
    public function __invoke(StoreArticleData $data): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => $data->title]));
    }
}

final class PlainArticlesController
{
    public function __invoke(Request $request): UserResource
    {
        $request->validate(['title' => ['required', 'string']]);

        return new UserResource(User::firstOrNew(['name' => $request->string('title')->value()]));
    }
}
