<?php
declare(strict_types=1);

use Bambamboole\Spectacular\OpenApi\Testing\ValidatesOpenApiSpec;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use League\OpenAPIValidation\PSR7\Exception\NoPath;
use PHPUnit\Framework\AssertionFailedError;
use ReflectionMethod;

uses(ValidatesOpenApiSpec::class, LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('spectacular.openapi.validation.path', dirname(__DIR__, 2).'/workbench/fixtures/openapi.json');
});

it('validates workbench API requests and responses', function (): void {
    $this->getJson('/api/categories')->assertSuccessful();
});

it('uses the generated schema location when no validation path is configured', function (): void {
    config()->set('spectacular.openapi.validation.path', '');

    expect((new ReflectionMethod($this, 'getOpenApiSpecPath'))->invoke($this))
        ->toBe(base_path('openapi.json'));
});

it('fails when a response does not satisfy its OpenAPI schema', function (): void {
    Route::get('api/categories', fn () => response()->json(['data' => 'invalid']));

    expect(fn () => $this->getJson('/api/categories'))
        ->toThrow(AssertionFailedError::class);
});

it('skips request and response validation for the next request only', function (): void {
    $withoutValidation = str_replace('X', '', 'withoutValidationX');

    $this->{$withoutValidation}()
        ->getJson('/api/not-documented')
        ->assertNotFound();

    expect(fn () => $this->getJson('/api/not-documented'))
        ->toThrow(NoPath::class);
});

it('skips added response codes', function (): void {
    Route::get('api/categories', fn () => response()->json(['data' => 'invalid'], 418));

    $skipResponseCode = str_replace('X', '', 'skipResponseCodeX');

    $this->{$skipResponseCode}(418)
        ->getJson('/api/categories')
        ->assertStatus(418);
});

it('spoofs a bearer token while Laravel handles an unauthenticated request', function (): void {
    $this->getJson('/api/users/1')->assertUnauthorized();
});
