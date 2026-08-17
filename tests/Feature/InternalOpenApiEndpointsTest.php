<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Attributes\SpecEndpoint;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

it('writes a public sibling without internal operations', function (): void {
    RouteFacade::get('api/shared', PublicEndpointController::class);
    RouteFacade::post('api/shared', InternalEndpointController::class);
    RouteFacade::delete('api/internal-only', InternalEndpointController::class);

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/shared', 'api/internal-only'], true));

    $directory = sys_get_temp_dir().'/spectacular-openapi-'.str_replace('.', '', uniqid('', true));
    $path = $directory.'/openapi.json';

    try {
        expect(Artisan::call('spectacular:openapi', ['--path' => $path]))->toBe(0)
            ->and($path)->toBeFile()
            ->and($directory.'/openapi.public.json')->toBeFile();

        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $publicDocument = json_decode((string) file_get_contents($directory.'/openapi.public.json'), true, flags: JSON_THROW_ON_ERROR);

        expect(data_get($document, 'paths./shared.get'))->toBeArray()
            ->and(data_get($document, 'paths./shared.post.x-internal'))->toBeTrue()
            ->and(data_get($document, 'paths./internal-only.delete.x-internal'))->toBeTrue()
            ->and(data_get($publicDocument, 'paths./shared.get'))->toBeArray()
            ->and(data_get($publicDocument, 'paths./shared.post'))->toBeNull()
            ->and(data_get($publicDocument, 'paths./internal-only'))->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('does not write a public sibling without internal operations', function (): void {
    RouteFacade::get('api/public-only', ExplicitlyPublicEndpointController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/public-only');

    $directory = sys_get_temp_dir().'/spectacular-openapi-'.str_replace('.', '', uniqid('', true));
    $path = $directory.'/openapi.json';

    try {
        expect(Artisan::call('spectacular:openapi', ['--path' => $path]))->toBe(0)
            ->and($path)->toBeFile()
            ->and($directory.'/openapi.public.json')->not->toBeFile();

        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect(data_get($document, 'paths./public-only.get.x-internal'))->toBeNull();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('keeps stdout as the complete document', function (): void {
    RouteFacade::get('api/internal-stdout', InternalEndpointController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/internal-stdout');

    expect(Artisan::call('spectacular:openapi'))->toBe(0)
        ->and(Artisan::output())->toContain('"x-internal": true');
});

final class PublicEndpointController
{
    /** @return array{status: string} */
    public function __invoke(): array
    {
        return ['status' => 'public'];
    }
}

final class InternalEndpointController
{
    /** @return array{status: string} */
    #[SpecEndpoint(internal: true)]
    public function __invoke(): array
    {
        return ['status' => 'internal'];
    }
}

final class ExplicitlyPublicEndpointController
{
    /** @return array{status: string} */
    #[SpecEndpoint]
    public function __invoke(): array
    {
        return ['status' => 'public'];
    }
}
