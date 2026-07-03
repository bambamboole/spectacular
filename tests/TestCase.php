<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\Tests;

use Bambamboole\Spectacular\SpectacularServiceProvider;
use Dedoc\Scramble\Infer\Context;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Scramble::throwOnError();
    }

    protected function tearDown(): void
    {
        Context::reset();

        Scramble::$tagResolver = null;
        Scramble::$enforceSchemaRules = [];
        Scramble::$defaultRoutesIgnored = false;
        Scramble::$extensions = [];

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('scramble.api_path', 'api');
        $app['config']->set('scramble.middleware', []);
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ScrambleServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SpectacularServiceProvider::class,
        ];
    }
}
