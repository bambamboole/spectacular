<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests;

use Bambamboole\Spectacular\SpectacularServiceProvider;
use Dedoc\Scramble\ScrambleServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;

/**
 * Registers this package before Scramble, which is the order Laravel's package
 * discovery produces (`bambamboole/*` sorts before `dedoc/*`). Scramble binds its
 * generator configuration while registering, so anything configured before that
 * would land on an instance the generator never sees.
 */
abstract class ProviderOrderTestCase extends TestCase
{
    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            SpectacularServiceProvider::class,
            LaravelDataServiceProvider::class,
            ScrambleServiceProvider::class,
            QueryBuilderServiceProvider::class,
        ];
    }
}
