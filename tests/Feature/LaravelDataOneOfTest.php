<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Workbench\App\Data\StorePageData;

it('documents a property-morphable collection as a discriminated oneOf', function (): void {
    $blocks = generatedPageSchemas()['StorePageData']['properties']['blocks'];

    expect($blocks['items']['oneOf'] ?? null)->toBe([
        ['$ref' => '#/components/schemas/TextBlockData'],
        ['$ref' => '#/components/schemas/ImageBlockData'],
    ])->and($blocks['items']['discriminator'] ?? null)->toBe([
        'propertyName' => 'type',
        'mapping' => [
            'text' => '#/components/schemas/TextBlockData',
            'image' => '#/components/schemas/ImageBlockData',
        ],
    ]);
});

it('documents each variant with its discriminator pinned to the selecting value', function (): void {
    $schemas = generatedPageSchemas();

    expect($schemas['TextBlockData']['properties']['type'])->toBe(['type' => 'string', 'enum' => ['text']])
        ->and($schemas['TextBlockData']['properties']['type']['enum'] ?? null)->toBe(['text'])
        ->and($schemas['TextBlockData']['required'] ?? null)->toBe(['text', 'type'])
        ->and($schemas['TextBlockData']['properties']['text']['description'] ?? null)->toBe('Rich text of the block.')
        ->and($schemas['ImageBlockData']['properties']['type']['enum'] ?? null)->toBe(['image'])
        ->and($schemas['ImageBlockData']['required'] ?? null)->toBe(['url', 'type']);
});

it('documents a data response with the request side component schema', function (): void {
    RouteFacade::post('api/pages', StorePageController::class);
    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/pages');
    $document = app(Generator::class)();

    expect(data_get($document, 'paths./pages.post.responses.200.content.application/json.schema.$ref'))
        ->toBe('#/components/schemas/StorePageData');
});

it('hydrates each payload item into the variant its discriminator selects', function (): void {
    RouteFacade::post('api/pages', StorePageController::class);

    $this->postJson('/api/pages', [
        'title' => 'Landing',
        'blocks' => [
            ['type' => 'text', 'text' => 'Welcome'],
            ['type' => 'image', 'url' => 'https://example.test/hero.png'],
        ],
    ])->assertSuccessful()
        ->assertJsonPath('blocks.0.text', 'Welcome')
        ->assertJsonPath('blocks.1.url', 'https://example.test/hero.png');
});

it('rejects an unknown discriminator value', function (): void {
    RouteFacade::post('api/pages', StorePageController::class);

    $this->postJson('/api/pages', [
        'title' => 'Landing',
        'blocks' => [['type' => 'video', 'url' => 'x']],
    ])->assertUnprocessable();
});

/**
 * @return array<string, array<string, mixed>>
 */
function generatedPageSchemas(): array
{
    RouteFacade::post('api/pages', StorePageController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/pages');
    $document = app(Generator::class)();

    $schemas = data_get($document, 'components.schemas');

    return is_array($schemas) ? $schemas : [];
}

final class StorePageController
{
    public function __invoke(StorePageData $data): StorePageData
    {
        return $data;
    }
}
