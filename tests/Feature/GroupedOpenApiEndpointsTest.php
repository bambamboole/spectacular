<?php
declare(strict_types=1);

use Bambamboole\Spectacular\Attributes\SpecEndpoint;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;

it('writes a sibling per group while keeping the complete document', function (string $filename): void {
    RouteFacade::get('api/shared', UngroupedEndpointController::class);
    RouteFacade::post('api/shared', InternalEndpointController::class);
    RouteFacade::delete('api/internal-only', InternalEndpointController::class);
    RouteFacade::put('api/shared', PartnerEndpointController::class);
    RouteFacade::patch('api/shared', NumericGroupEndpointController::class);

    Scramble::routes(fn (Route $route): bool => in_array($route->uri(), ['api/shared', 'api/internal-only'], true));

    $directory = sys_get_temp_dir().'/spectacular-openapi-'.str_replace('.', '', uniqid('', true));
    $path = $directory.'/'.$filename;

    try {
        expect(Artisan::call('spectacular:openapi', ['--path' => $path]))->toBe(0)
            ->and($path)->toBeFile()
            ->and($directory.'/openapi.internal.json')->toBeFile();

        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $groupDocument = json_decode((string) file_get_contents($directory.'/openapi.internal.json'), true, flags: JSON_THROW_ON_ERROR);

        expect(data_get($document, 'paths./shared.get'))->toBeArray()
            ->and(data_get($document, 'paths./shared.post.x-group'))->toBe('internal')
            ->and(data_get($document, 'paths./internal-only.delete.x-group'))->toBe('internal')
            ->and(data_get($groupDocument, 'paths./shared.get'))->toBeNull()
            ->and(data_get($groupDocument, 'paths./shared.post'))->toBe(data_get($document, 'paths./shared.post'))
            ->and(data_get($groupDocument, 'paths./internal-only.delete'))->toBeArray()
            ->and(data_get($groupDocument, 'paths./shared.put'))->toBeNull()
            ->and(data_get($groupDocument, 'paths./shared.patch'))->toBeNull();

        $metadata = array_diff_key($document, ['paths' => true]);
        expect(array_diff_key($groupDocument, ['paths' => true]))->toBe($metadata);

        foreach (['partner%2Fadmin' => 'put', '0' => 'patch'] as $group => $method) {
            $sibling = json_decode((string) file_get_contents($directory.'/openapi.'.$group.'.json'), true, flags: JSON_THROW_ON_ERROR);

            expect($sibling['paths'])->toBe(['/shared' => [$method => $document['paths']['/shared'][$method]]]);
            expect(array_diff_key($sibling, ['paths' => true]))->toBe($metadata);
        }
    } finally {
        File::deleteDirectory($directory);
    }
})->with(['openapi.json', 'openapi']);

it('does not write siblings without grouped operations', function (): void {
    RouteFacade::get('api/public-only', ExplicitlyUngroupedEndpointController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/public-only');

    $directory = sys_get_temp_dir().'/spectacular-openapi-'.str_replace('.', '', uniqid('', true));
    $path = $directory.'/openapi.json';

    try {
        expect(Artisan::call('spectacular:openapi', ['--path' => $path]))->toBe(0)
            ->and($path)->toBeFile()
            ->and($directory.'/openapi.internal.json')->not->toBeFile();

        $document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        expect(data_get($document, 'paths./public-only.get.x-group'))->toBeNull()
            ->and(glob($directory.'/*'))->toBe([$path]);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('keeps stdout as the complete document', function (): void {
    RouteFacade::get('api/internal-stdout', InternalEndpointController::class);

    Scramble::routes(fn (Route $route): bool => $route->uri() === 'api/internal-stdout');

    expect(Artisan::call('spectacular:openapi'))->toBe(0)
        ->and(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['paths']['/internal-stdout']['get']['x-group'])->toBe('internal');
});

final class UngroupedEndpointController
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
    #[SpecEndpoint(group: EndpointGroup::Internal)]
    public function __invoke(): array
    {
        return ['status' => 'internal'];
    }
}

final class ExplicitlyUngroupedEndpointController
{
    /** @return array{status: string} */
    #[SpecEndpoint]
    public function __invoke(): array
    {
        return ['status' => 'public'];
    }
}

enum EndpointGroup: string
{
    case Internal = 'internal';
}

enum NumericEndpointGroup: int
{
    case Zero = 0;
}

final class PartnerEndpointController
{
    /** @return array{status: string} */
    #[SpecEndpoint(group: 'partner/admin')]
    public function __invoke(): array
    {
        return ['status' => 'partner'];
    }
}

final class NumericGroupEndpointController
{
    /** @return array{status: string} */
    #[SpecEndpoint(group: NumericEndpointGroup::Zero)]
    public function __invoke(): array
    {
        return ['status' => 'numeric'];
    }
}
