<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Tests\Fixtures\ModelStates\Order;
use Bambamboole\Spectacular\Tests\Fixtures\ModelStates\OrderResource;
use Bambamboole\Spectacular\Tests\Fixtures\ModelStates\OrderShipmentResource;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('orders', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('status');
        $table->timestamps();
    });
});

it('documents a model state property as a shared string enum schema', function (): void {
    RouteFacade::get('api/orders', OrdersController::class);

    $document = generatedModelStateOpenApiDocumentForUri('api/orders');

    expect($document['components']['schemas']['OrderState'] ?? null)
        ->toMatchArray([
            'type' => 'string',
            'enum' => ['pending', 'shipped'],
        ])
        ->and($document['components']['schemas']['OrderResource']['properties']['status'] ?? null)
        ->toBe(['$ref' => '#/components/schemas/OrderState']);
});

it('documents a concrete state under the base state schema', function (): void {
    RouteFacade::get('api/orders/shipment', OrderShipmentController::class);

    $document = generatedModelStateOpenApiDocumentForUri('api/orders/shipment');

    expect($document['components']['schemas']['OrderShipmentResource']['properties']['status'] ?? null)
        ->toBe(['$ref' => '#/components/schemas/OrderState'])
        ->and($document['components']['schemas']['OrderState']['enum'] ?? null)
        ->toBe(['pending', 'shipped']);
});

/**
 * @return array<string, mixed>
 */
function generatedModelStateOpenApiDocumentForUri(string $uri): array
{
    Scramble::routes(fn (Route $route): bool => $route->uri() === $uri);

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

final class OrdersController
{
    public function __invoke(): OrderResource
    {
        return new OrderResource(Order::query()->firstOrFail());
    }
}

final class OrderShipmentController
{
    public function __invoke(): OrderShipmentResource
    {
        return new OrderShipmentResource(Order::query()->firstOrFail());
    }
}
