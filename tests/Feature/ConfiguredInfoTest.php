<?php
declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;

it('leaves the info object to scramble when nothing is configured', function (): void {
    config()->set('scramble.info.version', '3.2.1');
    config()->set('scramble.info.description', 'Resolved by scramble.');

    expect(generatedInfo([]))
        ->toBe([
            'title' => config('app.name'),
            'version' => '3.2.1',
            'description' => 'Resolved by scramble.',
        ]);
});

it('documents the full info object from configuration', function (): void {
    $info = generatedInfo([
        'title' => 'Acme API',
        'version' => '2.1.0',
        'description' => 'What this API is for.',
        'terms_of_service' => 'https://acme.test/terms',
        'contact' => ['name' => 'API support', 'email' => 'api@acme.test', 'url' => 'https://acme.test/support'],
        'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
    ]);

    expect($info)->toBe([
        'title' => 'Acme API',
        'version' => '2.1.0',
        'description' => 'What this API is for.',
        'termsOfService' => 'https://acme.test/terms',
        'contact' => ['name' => 'API support', 'url' => 'https://acme.test/support', 'email' => 'api@acme.test'],
        'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
    ]);
});

it('keeps what scramble resolved for the keys it is not given', function (): void {
    config()->set('scramble.info.version', '3.2.1');

    $info = generatedInfo(['contact' => ['email' => 'api@acme.test']]);

    expect($info)->toBe([
        'title' => config('app.name'),
        'version' => '3.2.1',
        'contact' => ['email' => 'api@acme.test'],
    ]);
});

it('drops a licence without a name and a url alongside an spdx identifier', function (array $license, array $expected): void {
    expect(generatedInfo(['license' => $license])['license'] ?? [])->toBe($expected);
})->with([
    'no name' => [['url' => 'https://acme.test/licence'], []],
    'identifier wins over url' => [
        ['name' => 'MIT', 'identifier' => 'MIT', 'url' => 'https://acme.test/licence'],
        ['name' => 'MIT', 'identifier' => 'MIT'],
    ],
    'url without an identifier' => [
        ['name' => 'Proprietary', 'url' => 'https://acme.test/licence'],
        ['name' => 'Proprietary', 'url' => 'https://acme.test/licence'],
    ],
]);

/**
 * @param  array<string, mixed>  $info
 * @return array<string, mixed>
 */
function generatedInfo(array $info): array
{
    config()->set('spectacular.openapi.info', $info);

    Scramble::configure()->useConfig(config('scramble'));
    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/roles');

    $document = app(Generator::class)();

    return is_array($document) && is_array($document['info'] ?? null) ? $document['info'] : [];
}
