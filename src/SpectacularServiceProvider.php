<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Console\GenerateAsyncApiCommand;
use Bambamboole\Spectacular\AsyncApi\Messages\MessageDefinitionFactory;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\LaravelData\BigDecimalCast;
use Bambamboole\Spectacular\LaravelData\BigDecimalRuleInferrer;
use Bambamboole\Spectacular\LaravelData\BigDecimalTransformer;
use Bambamboole\Spectacular\OpenApi\Console\GenerateOpenApiCommand;
use Bambamboole\Spectacular\OpenApi\Extensions\DataToSchemaExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\ModelStateToSchemaExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\PaginationExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\QueryBuilderExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\SpecEndpointExtension;
use Bambamboole\Spectacular\OpenApi\Extensions\SpecParameterExtension;
use Bambamboole\Spectacular\OpenApi\Info\DocumentsConfiguredInfo;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataParametersExtractor;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataRequiredFieldsTransformer;
use Bambamboole\Spectacular\OpenApi\ModelStates\StateTransitionOperations;
use Bambamboole\Spectacular\OpenApi\RateLimiting\RateLimitResponses;
use Bambamboole\Spectacular\OpenApi\Security\DocumentsConfiguredSecurity;
use Bambamboole\Spectacular\OpenApi\Security\MarksUnauthenticatedRoutesPublic;
use Bambamboole\Spectacular\OpenApi\Security\SecurityConfig;
use Bambamboole\Spectacular\OpenApi\Transformers\ValidationErrorResponses;
use Bambamboole\Spectacular\Support\ClassDiscoverer;
use Brick\Math\BigDecimal;
use Dedoc\Scramble\Configuration\ParametersExtractors;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelData\Data;
use Spatie\ModelStates\State;

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

        if (class_exists(State::class)) {
            Scramble::registerExtension(ModelStateToSchemaExtension::class);
        }

        if (class_exists(Data::class)) {
            Scramble::registerExtension(DataToSchemaExtension::class);
        }

        Scramble::registerExtension(SpecEndpointExtension::class);

        // The query builder and pagination extensions replace the operation parameters
        // they generate, so documenting a parameter has to happen after them. Scramble
        // appends registered extensions as one batch in registration order, which holds
        // that order no matter which of the two providers boots first.
        Scramble::registerExtension(SpecParameterExtension::class);
    }

    public function boot(): void
    {
        $this->configureScramble();
        $this->registerBigDecimalDataSupport();

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'spectacular');

        $this->publishes([
            __DIR__.'/../config/spectacular.php' => config_path('spectacular.php'),
        ], 'spectacular-config');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/spectacular'),
        ], 'spectacular-lang');

        $this->commands([
            GenerateAsyncApiCommand::class,
            GenerateOpenApiCommand::class,
        ]);
    }

    /**
     * Scramble keeps its generator configuration in a container singleton that its
     * own provider binds while registering. Package discovery may register this
     * provider first, so configuring Scramble here rather than in register() is
     * what keeps these transformers on the instance the generator later uses.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(DocumentsConfiguredInfo::class)
            ->withOperationTransformers([
                ValidationErrorResponses::class,
                RateLimitResponses::class,
            ]);

        if (SecurityConfig::schemes() !== []) {
            Scramble::configure()
                ->withDocumentTransformers(DocumentsConfiguredSecurity::class)
                ->withOperationTransformers(MarksUnauthenticatedRoutesPublic::class);
        }

        if (class_exists(Data::class)) {
            Scramble::configure()
                ->withParametersExtractors(fn (ParametersExtractors $extractors): ParametersExtractors => $extractors->prepend(DataParametersExtractor::class))
                ->withOperationTransformers(DataRequiredFieldsTransformer::class);
        }

        if (class_exists(State::class)) {
            Scramble::configure()->withDocumentTransformers(StateTransitionOperations::class);
        }
    }

    /**
     * Runs in boot() so laravel-data has already merged its config defaults —
     * setting the keys during register() would race provider order and could
     * shadow the default DateTimeInterface entries. An application entry for
     * BigDecimal always wins over the package one.
     */
    private function registerBigDecimalDataSupport(): void
    {
        if (! class_exists(Data::class) || ! class_exists(BigDecimal::class)) {
            return;
        }

        config()->set('data.casts', config('data.casts', []) + [BigDecimal::class => BigDecimalCast::class]);
        config()->set('data.transformers', config('data.transformers', []) + [BigDecimal::class => BigDecimalTransformer::class]);

        $inferrers = config('data.rule_inferrers', []);

        if (! in_array(BigDecimalRuleInferrer::class, $inferrers, true)) {
            config()->set('data.rule_inferrers', [...$inferrers, BigDecimalRuleInferrer::class]);
        }
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
