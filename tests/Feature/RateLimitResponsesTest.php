<?php
declare(strict_types=1);

use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

it('documents the rate limit budget and the exhausted response on a throttled route', function (): void {
    $document = generatedRateLimitDocument();
    $operation = $document['paths']['/categories']['get'];

    expect($operation['responses'])->toHaveKey(429)
        ->and($operation['responses'][429])->toBe(['$ref' => '#/components/responses/ThrottleRequestsException'])
        ->and($operation['responses'][200]['headers'])
        ->toBe([
            'X-RateLimit-Limit' => [
                'description' => 'The maximum number of requests allowed in the current window.',
                'schema' => ['type' => 'integer'],
            ],
            'X-RateLimit-Remaining' => [
                'description' => 'The number of requests left in the current window.',
                'schema' => ['type' => 'integer'],
            ],
        ]);

    $exhausted = $document['components']['responses']['ThrottleRequestsException'];

    expect($exhausted['description'])->toBe('Rate limit exceeded')
        ->and(array_keys($exhausted['headers']))
        ->toBe(['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After', 'X-RateLimit-Reset'])
        ->and($exhausted['content']['application/json']['schema'])
        ->toBe([
            'type' => 'object',
            'properties' => ['message' => ['type' => 'string', 'description' => 'Error overview.']],
            'required' => ['message'],
        ]);
});

it('leaves a route without throttling middleware alone', function (): void {
    $operation = generatedRateLimitDocument()['paths']['/roles']['get'];

    expect($operation['responses'])->not->toHaveKey(429)
        ->and($operation['responses'][200])->not->toHaveKey('headers');
});

it('documents only the throttling middleware it is configured with', function (): void {
    config()->set('spectacular.openapi.rate_limiting.middleware', ['throttle.api']);

    RouteFacade::get('api/tenant-metrics', TenantMetricsController::class)
        ->middleware('throttle.api')
        ->name('api.tenant-metrics');

    $document = generatedRateLimitDocument('api/categories', 'api/tenant-metrics');

    expect($document['paths']['/categories']['get']['responses'])->not->toHaveKey(429)
        ->and($document['paths']['/tenant-metrics']['get']['responses'])->toHaveKey(429);
});

it('documents no rate limit when the headers are cleared', function (): void {
    config()->set('spectacular.openapi.rate_limiting.headers', []);

    $operation = generatedRateLimitDocument()['paths']['/categories']['get'];

    expect($operation['responses'])->not->toHaveKey(429)
        ->and($operation['responses'][200])->not->toHaveKey('headers');
});

it('keeps a rate limit an endpoint documents itself', function (): void {
    RouteFacade::get('api/documented-limit', DocumentedLimitController::class)
        ->middleware('throttle:5,1')
        ->name('api.documented-limit');

    $operation = generatedRateLimitDocument('api/documented-limit')['paths']['/documented-limit']['get'];

    expect($operation['responses'][429]['description'])->toBe('Slow down');
});

/**
 * @return array<string, mixed>
 */
function generatedRateLimitDocument(string ...$uris): array
{
    $uris = $uris === [] ? ['api/categories', 'api/roles'] : $uris;

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), $uris, true));

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

final class TenantMetricsController
{
    /**
     * @return array<string, int>
     */
    public function __invoke(): array
    {
        return ['requests' => 1];
    }
}

final class DocumentedLimitController
{
    /**
     * @return array<string, string>
     */
    #[Response(429, description: 'Slow down')]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
