<?php
declare(strict_types=1);

use Bambamboole\Spectacular\LaravelData\BigDecimalCast;
use Bambamboole\Spectacular\LaravelData\BigDecimalTransformer;
use Brick\Math\BigDecimal;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;
use Workbench\App\Data\StoreProductData;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('registers the BigDecimal cast and transformer without application config', function (): void {
    expect(config('data.casts'))->toHaveKey(BigDecimal::class, BigDecimalCast::class)
        ->and(config('data.transformers'))->toHaveKey(BigDecimal::class, BigDecimalTransformer::class);
});

it('hydrates a BigDecimal property from numeric string, int, and float payloads', function (mixed $payload, string $expected): void {
    $data = StoreProductData::validateAndCreate(['name' => 'Widget', 'price' => $payload]);

    expect((string) $data->price)->toBe($expected);
})->with([
    'numeric string' => ['19.99', '19.99'],
    'int' => [20, '20'],
    'float' => [19.5, '19.5'],
]);

it('rejects a non-numeric payload value for a BigDecimal property', function (): void {
    StoreProductData::validateAndCreate(['name' => 'Widget', 'price' => 'not-a-number']);
})->throws(ValidationException::class);

it('serializes a BigDecimal property to its plain decimal string', function (): void {
    $data = StoreProductData::validateAndCreate(['name' => 'Widget', 'price' => '19.99']);

    expect($data->toArray()['price'])->toBe('19.99');
});

it('documents a BigDecimal property as number or decimal string', function (): void {
    $price = generatedProductSchema()['properties']['price'];

    expect($price['anyOf'] ?? null)->toBe([
        ['type' => 'number'],
        ['type' => 'string', 'pattern' => '^-?\d+(\.\d+)?$'],
    ])->and($price['description'] ?? null)->toBe('Net price per unit.');
});

it('keeps the null of a nullable BigDecimal property', function (): void {
    $weight = generatedProductSchema()['properties']['weight'];

    expect($weight['anyOf'][2] ?? null)->toBe(['type' => 'null']);
});

it('keeps numeric constraints on the number branch of a BigDecimal property', function (): void {
    $discount = generatedProductSchema()['properties']['discount_percent'];

    expect($discount['anyOf'][0] ?? null)->toBe(['type' => 'number', 'maximum' => 100.0]);
});

it('requires only the BigDecimal properties a client has to send', function (): void {
    expect(generatedProductSchema()['required'] ?? [])->toBe(['name', 'price']);
});

/**
 * @return array<string, mixed>
 */
function generatedProductSchema(): array
{
    RouteFacade::post('api/products', StoreProductController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/products');
    $document = app(Generator::class)();

    return data_get($document, 'components.schemas.StoreProductData', []);
}

final class StoreProductController
{
    public function __invoke(StoreProductData $data): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => $data->name]));
    }
}
