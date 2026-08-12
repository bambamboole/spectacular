<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Console\GenerateAsyncApiCommand;
use Bambamboole\Spectacular\AsyncApi\Messages\MessageDefinitionFactory;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\OpenApi\Console\GenerateOpenApiCommand;
use Bambamboole\Spectacular\OpenApi\Extensions\PaginationExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\QueryBuilderExtension;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataParametersExtractor;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataRequiredFieldsTransformer;
use Bambamboole\Spectacular\OpenApi\Transformers\ValidationErrorResponses;
use Bambamboole\Spectacular\Support\ClassDiscoverer;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelData\Data;

final class SpectacularServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/spectacular.php', 'spectacular');
        $this->mergeAsyncApiWebhookConfigDefaults();

        $this->app->singleton(PayloadSchemaFactory::class);
        $this->app->singleton(MessageDefinitionFactory::class);
        $this->app->singleton(AsyncApiGenerator::class, fn (Application $app): AsyncApiGenerator => new AsyncApiGenerator(
            new ClassDiscoverer,
            $app->make(MessageDefinitionFactory::class),
            class_exists(WebhookEventRegistry::class) ? $app->make(WebhookEventRegistry::class) : null,
        ));

        Scramble::registerExtension(QueryBuilderExtension::class);
        Scramble::registerExtension(PaginationExtension::class);

        Scramble::configure()->withOperationTransformers(ValidationErrorResponses::class);

        if (class_exists(Data::class)) {
            Scramble::configure()
                ->withParametersExtractors(fn (ParametersExtractors $extractors): ParametersExtractors => $extractors->prepend(DataParametersExtractor::class))
                ->withOperationTransformers(DataRequiredFieldsTransformer::class);
        }
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
