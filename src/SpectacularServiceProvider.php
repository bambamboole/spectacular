<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Console\GenerateAsyncApiCommand;
use Bambamboole\Spectacular\AsyncApi\Support\ClassDiscoverer;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\OpenApi\Console\GenerateOpenApiCommand;
use Dedoc\Scramble\Scramble;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SpectacularServiceProvider extends PackageServiceProvider
{
    public static string $name = 'spectacular';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasCommands([
                GenerateAsyncApiCommand::class,
                GenerateOpenApiCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ClassDiscoverer::class);
        $this->app->singleton(PayloadSchemaFactory::class);
        $this->app->singleton(AsyncApiGenerator::class);

        foreach (config('spectacular.scramble.extensions', []) as $extension) {
            if (is_string($extension)) {
                Scramble::registerExtension($extension);
            }
        }
    }
}
