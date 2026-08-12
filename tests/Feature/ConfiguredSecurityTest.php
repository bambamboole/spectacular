<?php
declare(strict_types=1);

use Bambamboole\Spectacular\OpenApi\Security\DocumentsConfiguredSecurity;
use Bambamboole\Spectacular\OpenApi\Security\MarksUnauthenticatedRoutesPublic;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;

it('offers no security of its own when nothing is configured', function (): void {
    $document = generatedSecurityDocument([]);

    expect($document['components']['securitySchemes'] ?? [])->toBe([])
        ->and($document['security'] ?? null)->toBeNull();
});

it('documents a bearer token from configuration', function (): void {
    $document = generatedSecurityDocument([
        'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'A personal access token.'],
    ]);

    expect($document['components']['securitySchemes']['bearer'])
        ->toBe(['type' => 'http', 'description' => 'A personal access token.', 'scheme' => 'bearer'])
        ->and($document['security'])->toBe([['bearer' => []]]);
});

it('documents oauth2 flows with their urls and scopes', function (): void {
    $document = generatedSecurityDocument([
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorization_url' => '/oauth/authorize',
                    'token_url' => 'https://id.example.com/oauth/token',
                    'scopes' => ['users:read' => 'Read users'],
                ],
                'clientCredentials' => ['token_url' => '/oauth/token'],
            ],
        ],
    ]);

    $flows = $document['components']['securitySchemes']['oauth2']['flows'];

    expect($flows['authorizationCode']['authorizationUrl'])->toBe('http://localhost/oauth/authorize')
        ->and($flows['authorizationCode']['tokenUrl'])->toBe('https://id.example.com/oauth/token')
        ->and($flows['authorizationCode']['scopes'])->toBe(['users:read' => 'Read users'])
        ->and($flows['clientCredentials']['tokenUrl'])->toBe('http://localhost/oauth/token');
});

it('resolves scopes an app derives at runtime through the container', function (): void {
    $document = generatedSecurityDocument([
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => ['token_url' => '/oauth/token', 'scopes' => ResolvedScopes::class],
            ],
        ],
    ]);

    expect($document['components']['securitySchemes']['oauth2']['flows']['authorizationCode']['scopes'])
        ->toBe(['resolved:scope' => 'Resolved through the container.']);
});

it('documents an openid connect discovery url', function (): void {
    $document = generatedSecurityDocument([
        'oidc' => ['type' => 'openIdConnect', 'url' => '/.well-known/openid-configuration'],
    ]);

    expect($document['components']['securitySchemes']['oidc'])
        ->toBe(['type' => 'openIdConnect', 'openIdConnectUrl' => 'http://localhost/.well-known/openid-configuration']);
});

it('offers several modes as alternatives', function (): void {
    $document = generatedSecurityDocument([
        'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
        'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
    ]);

    expect(array_keys($document['components']['securitySchemes']))->toBe(['bearer', 'apiKey'])
        ->and($document['security'])->toBe([['bearer' => []], ['apiKey' => []]]);
});

it('documents a route without authentication middleware as public', function (): void {
    $document = generatedSecurityDocument(['bearer' => ['type' => 'http', 'scheme' => 'bearer']]);

    expect(data_get($document, 'paths./roles.get.security'))->toBe([])
        ->and(data_get($document, 'paths./users/{user}.get.security'))->toBeNull();
});

it('rejects a scheme it cannot build', function (array $scheme, string $message): void {
    expect(fn (): array => generatedSecurityDocument(['broken' => $scheme]))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown type' => [['type' => 'carrier-pigeon'], 'Security scheme [broken] has an unknown type [carrier-pigeon].'],
    'oauth2 without flows' => [['type' => 'oauth2'], 'Security scheme [broken] of type [oauth2] requires at least one flow.'],
    'openIdConnect without url' => [['type' => 'openIdConnect'], 'Security scheme [broken] of type [openIdConnect] requires a `url`.'],
]);

/**
 * @param  array<string, array<string, mixed>>  $schemes
 * @return array<string, mixed>
 */
function generatedSecurityDocument(array $schemes): array
{
    config()->set('spectacular.openapi.security.schemes', $schemes);
    config()->set('spectacular.openapi.security.middleware', ['auth', 'auth:*']);

    if ($schemes !== []) {
        Scramble::configure()
            ->withDocumentTransformers(DocumentsConfiguredSecurity::class)
            ->withOperationTransformers(MarksUnauthenticatedRoutesPublic::class);
    }

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/roles', 'api/users/{user}'], true));

    $document = app(Generator::class)();

    return is_array($document) ? $document : [];
}

final class ResolvedScopes
{
    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        return ['resolved:scope' => 'Resolved through the container.'];
    }
}
