<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\User;

it('documents a validation error on writing endpoints', function (string $method): void {
    RouteFacade::{$method}('api/widgets', WidgetsController::class);

    $operation = generatedWidgetsOperation($method);

    expect($operation['responses']['422']['$ref'] ?? null)
        ->toBe('#/components/responses/ValidationException');
})->with(['post', 'put', 'patch']);

it('leaves reading and deleting endpoints alone', function (string $method): void {
    RouteFacade::{$method}('api/widgets', WidgetsController::class);

    $codes = array_map(strval(...), array_keys(generatedWidgetsOperation($method)['responses'] ?? []));

    expect($codes)->not->toBeEmpty()->and($codes)->not->toContain('422');
})->with(['get', 'delete']);

it('describes the validation error body once for the whole document', function (): void {
    RouteFacade::post('api/widgets', WidgetsController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/widgets');
    $document = app(Generator::class)();

    $response = data_get($document, 'components.responses.ValidationException');

    expect($response['description'])->toBe('Validation error')
        ->and(array_keys($response['content']['application/json']['schema']['properties']))->toBe(['message', 'errors'])
        ->and($response['content']['application/json']['schema']['required'])->toBe(['message', 'errors']);
});

it('does not add a second validation error to an endpoint that already documents one', function (): void {
    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/users' && $route->methods()[0] === 'POST');
    $document = app(Generator::class)();

    $codes = array_map(strval(...), array_keys(data_get($document, 'paths./users.post.responses', [])));

    expect(array_filter($codes, fn (string $code): bool => $code === '422'))->toHaveCount(1);
});

/**
 * @return array<string, mixed>
 */
function generatedWidgetsOperation(string $method): array
{
    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/widgets');
    $document = app(Generator::class)();

    $operation = data_get($document, "paths./widgets.{$method}");

    return is_array($operation) ? $operation : [];
}

final class WidgetsController
{
    public function __invoke(Request $request): UserResource
    {
        return new UserResource(User::firstOrNew(['name' => 'widget']));
    }
}
