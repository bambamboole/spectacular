<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular;

use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Console\GenerateAsyncApiCommand;
use Bambamboole\Spectacular\AsyncApi\Messages\MessageDefinitionFactory;
use Bambamboole\Spectacular\AsyncApi\Support\ClassDiscoverer;
use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\OpenApi\Console\GenerateOpenApiCommand;
use Bambamboole\Spectacular\Webhooks\NullWebhookSubscriptionRepository;
use Bambamboole\Spectacular\Webhooks\WebhookEventRegistry;
use Bambamboole\Spectacular\Webhooks\WebhookPayloadFactory;
use Bambamboole\Spectacular\Webhooks\WebhookSubscriptionRepository;
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
        $this->mergeAsyncApiWebhookConfigDefaults();

        $this->app->singleton(ClassDiscoverer::class);
        $this->app->singleton(PayloadSchemaFactory::class);
        $this->app->singleton(MessageDefinitionFactory::class);
        $this->app->singleton(AsyncApiGenerator::class);
        $this->app->singleton(WebhookEventRegistry::class);
        $this->app->singleton(WebhookPayloadFactory::class);
        $this->app->bindIf(WebhookSubscriptionRepository::class, NullWebhookSubscriptionRepository::class);

        foreach (config('spectacular.scramble.extensions', []) as $extension) {
            if (is_string($extension)) {
                Scramble::registerExtension($extension);
            }
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
