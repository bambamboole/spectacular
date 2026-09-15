<?php
declare(strict_types=1);

use Bambamboole\Spectacular\OpenApi\Endpoint;
use Bambamboole\Spectacular\OpenApi\EndpointDefinition;
use Bambamboole\Spectacular\OpenApi\Transformers\DocumentsEndpoints;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Parameter;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::match(['GET', 'POST'], 'realms/{realm}/oauth/token', ExplicitTokenController::class)->name('oidc.token');
    Route::get('api/existing', ExplicitTokenController::class)->name('existing');
    config()->set('spectacular.openapi.endpoints', [OAuthEndpointDefinition::class]);
    Scramble::routes(fn ($route): bool => $route->uri() === 'api/existing');
});

it('injects form bodies, parameters and responses outside the API prefix for each method', function (): void {
    $spec = app(Generator::class)();
    $token = $spec['paths']['/realms/{realm}/oauth/token'];
    expect($token['post']['requestBody']['content'])->toHaveKey('application/x-www-form-urlencoded')
        ->and($token['post']['responses'])->toHaveKeys([200, 400])->not->toHaveKey(422)
        ->and($token['post']['security'])->toBe([])
        ->and($token['post']['servers'][0]['url'])->toBe(url('/'))
        ->and($token['get']['parameters'])->toContain(['name' => 'scope', 'in' => 'query', 'schema' => ['type' => 'string']])
        ->and($token['get']['parameters'][0]['name'])->toBe('realm')
        ->and($spec['paths'])->toHaveKey('/existing')
        ->and($spec['components']['schemas']['Token']['required'])->toBe(['access_token']);
});

it('keeps explicit fields after later document transformers and repeated generation', function (): void {
    Scramble::configure()->withDocumentTransformers(function ($document): void {
        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $operation->summary('Inferred summary');
                $operation->security = null;
            }
        }
    });
    $generator = app(Generator::class);
    $first = $generator();
    expect($generator())->toBe($first)
        ->and($first['paths']['/realms/{realm}/oauth/token']['post']['summary'])->toBe('Issue a token');
});

it('rejects unknown routes and missing references', function (array $operation, string $route, string $message): void {
    app()->bind(OAuthEndpointDefinition::class, fn () => new class($operation, $route) implements EndpointDefinition
    {
        /** @param array<string, mixed> $operation */
        public function __construct(private array $operation, private string $route) {}

        public function endpoints(): array
        {
            return [Endpoint::route($this->route, 'POST', $this->operation)];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    expect(fn () => app(Generator::class)())->toThrow(InvalidArgumentException::class, $message);
})->with([
    [[], 'missing', 'must match exactly one'],
    [['requestBody' => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Missing']]]]], 'oidc.token', 'Unresolved endpoint reference'],
]);

it('expands optional realm paths without renaming controller arguments', function (): void {
    Route::get('realms/{realm}/metadata/{path?}', OptionalMetadataController::class)->name('metadata');
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('metadata', 'GET', ['operationId' => 'metadata', 'tags' => ['Discovery'], 'security' => [], 'parameters' => [
                ['in' => 'path', 'name' => 'realm', 'schema' => ['type' => 'string', 'enum' => ['master']]],
                ['in' => 'path', 'name' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
            ]])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    $spec = app(Generator::class)();
    $short = $spec['paths']['/realms/{realm}/metadata']['get'];
    $long = $spec['paths']['/realms/{realm}/metadata/{path}']['get'];
    expect($short['parameters'])->toHaveCount(1)
        ->and($short['parameters'][0]['schema']['enum'])->toBe(['master'])
        ->and($short['parameters'][0]['required'])->toBeTrue()
        ->and($long['parameters'])->toHaveCount(2)
        ->and($short['operationId'])->not->toBe($long['operationId']);
});

it('preserves inferred path types and operation ids for summary overrides', function (): void {
    Route::get('api/items/{id}', TypedEndpointController::class)->name('items.show');
    Scramble::routes(fn ($route): bool => $route->getName() === 'items.show');
    config()->set('spectacular.openapi.endpoints', []);
    $before = app(Generator::class)()['paths']['/items/{id}']['get'];
    config()->set('spectacular.openapi.endpoints', [OAuthEndpointDefinition::class]);
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('items.show', 'GET', ['summary' => 'An item'])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    $after = app(Generator::class)()['paths']['/items/{id}']['get'];
    expect($after['parameters'])->toBe($before['parameters'])
        ->and($after['operationId'])->toBe($before['operationId'])
        ->and($after['summary'])->toBe('An item');
});

it('replaces bodies and responses and removes parameters without merging stale fields', function (): void {
    Scramble::configure()->withOperationTransformers(function ($operation): void {
        $operation->addParameters([Parameter::make('scope', 'query')->required(true)]);
    });
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('existing', 'GET', [
                'requestBody' => null,
                'parameters' => [['in' => 'query', 'name' => 'scope', 'x-remove' => true]],
                'responses' => [200 => ['description' => 'Replaced', 'headers' => ['Location' => ['schema' => ['type' => 'string']]]]],
            ])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    $operation = app(Generator::class)()['paths']['/existing']['get'];
    expect($operation)->not->toHaveKey('requestBody')
        ->and($operation['parameters'])->toBe([])
        ->and($operation['responses'][200])->not->toHaveKey('content')
        ->and($operation['responses'][200]['headers'])->toHaveKey('Location');
});

it('does not leak default endpoint definitions into named APIs', function (): void {
    $config = Scramble::registerApi('other', ['api_path' => 'api']);
    $config->withDocumentTransformers(DocumentsEndpoints::class);
    expect(app(Generator::class)($config)['paths'])->not->toHaveKey('/realms/{realm}/oauth/token');
    $config->useConfig(['api_path' => 'api', 'spectacular' => ['endpoints' => [OAuthEndpointDefinition::class]]]);
    expect(app(Generator::class)($config)['paths'])->toHaveKey('/realms/{realm}/oauth/token');
});

it('rejects duplicate resolved paths', function (): void {
    Route::get('optional/{path?}', OptionalMetadataController::class)->name('optional');
    Route::get('optional', OptionalMetadataController::class)->name('short');
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('optional', 'GET', []), Endpoint::route('short', 'GET', [])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    expect(fn () => app(Generator::class)())->toThrow(InvalidArgumentException::class, 'Duplicate resolved endpoint');
});

it('does not interpret example payloads as schema references', function (): void {
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('existing', 'GET', ['responses' => [200 => ['description' => 'Payload', 'content' => ['application/json' => ['example' => ['$ref' => '#/not-a-schema']]]]]])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    expect(app(Generator::class)()['paths']['/existing']['get']['responses'][200]['content']['application/json']['example'])->toBe(['$ref' => '#/not-a-schema']);
});

it('rejects an external route colliding with a different inferred API operation', function (): void {
    Route::post('api/oauth/token', ExplicitTokenController::class)->name('internal.token');
    Route::post('oauth/token', ExplicitTokenController::class)->name('external.token');
    Scramble::routes(fn ($route): bool => $route->getName() === 'internal.token');
    app()->bind(OAuthEndpointDefinition::class, fn () => new class implements EndpointDefinition
    {
        public function endpoints(): array
        {
            return [Endpoint::route('external.token', 'POST', [])];
        }

        public function schemas(): array
        {
            return [];
        }
    });
    expect(fn () => app(Generator::class)())->toThrow(InvalidArgumentException::class, 'collides with an existing operation');
});

final class TypedEndpointController
{
    /** @return array{id: int} */
    public function __invoke(int $id): array
    {
        return ['id' => $id];
    }
}

final class OptionalMetadataController
{
    /** @return array{path: string|null} */
    public function __invoke(?string $path = null): array
    {
        return ['path' => $path];
    }
}

final class OAuthEndpointDefinition implements EndpointDefinition
{
    public function endpoints(): array
    {
        return [
            Endpoint::route('oidc.token', 'POST', [
                'summary' => 'Issue a token', 'tags' => ['Auth'], 'security' => [],
                'requestBody' => ['required' => true, 'content' => ['application/x-www-form-urlencoded' => ['schema' => ['oneOf' => [
                    ['type' => 'object', 'required' => ['grant_type', 'code'], 'properties' => ['grant_type' => ['const' => 'authorization_code'], 'code' => ['type' => 'string']]],
                    ['type' => 'object', 'required' => ['grant_type'], 'properties' => ['grant_type' => ['const' => 'client_credentials']]],
                ]]]]],
                'responses' => [200 => ['description' => 'Token issued', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Token']]]], 400 => ['description' => 'Invalid grant'], 422 => null],
            ]),
            Endpoint::path('/realms/{realm}/oauth/token', 'GET', ['parameters' => [['name' => 'scope', 'in' => 'query', 'schema' => ['type' => 'string']]]]),
        ];
    }

    public function schemas(): array
    {
        return ['Token' => ['type' => 'object', 'required' => ['access_token'], 'properties' => ['access_token' => ['type' => 'string']]]];
    }
}

final class ExplicitTokenController
{
    /** @return array{status: string} */
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
