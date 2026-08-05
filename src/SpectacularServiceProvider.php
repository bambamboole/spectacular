<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Console\GenerateAsyncApiCommand;
use Bambamboole\Spectacular\AsyncApi\Messages\MessageDefinitionFactory;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\OpenApi\Console\GenerateOpenApiCommand;
use Bambamboole\Spectacular\OpenApi\Extensions\PaginationExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\QueryBuilderExtension;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

final class SpectacularServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/spectacular.php', 'spectacular');
        $this->mergeAsyncApiWebhookConfigDefaults();

        $this->app->singleton(PayloadSchemaFactory::class);
        $this->app->singleton(MessageDefinitionFactory::class);
        $this->app->singleton(AsyncApiGenerator::class);

        Scramble::registerExtension(QueryBuilderExtension::class);
        Scramble::registerExtension(PaginationExtension::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/spectacular.php' => config_path('spectacular.php'),
        ], 'spectacular-config');

        $this->commands([
            GenerateAsyncApiCommand::class,
            GenerateOpenApiCommand::class,
        ]);
    }

    private function mergeAsyncApiWebhookConfigDefaults(): void
    {
        if (config()->has('spectacular.asyncapi.webhooks')) {
            return;
        }

        $config = require __DIR__.'/../config/spectacular.php';

        config()->set('spectacular.asyncapi.webhooks', $config['asyncapi']['webhooks']);
    }
}
