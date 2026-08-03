<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\ServiceProvider;
use Inertia\Middleware as InertiaMiddleware;

final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('spectacular.asyncapi.info', [
            'title' => 'Workbench AsyncAPI',
            'version' => '1.0.0',
        ]);

        config()->set('spectacular.asyncapi.scan_paths', [
            dirname(__DIR__, 2).'/app/Events',
        ]);

        config(['lattice.discover' => [dirname(__DIR__)]]);

        config(['scramble.security_strategy' => MiddlewareAuthSecurityStrategy::class]);

        config()->set('webhooks.scan_paths', [
            dirname(__DIR__, 2).'/app/Events',
        ]);
        $this->app->forgetInstance(WebhookEventRegistry::class);
    }

    public function boot(Kernel $kernel): void
    {
        if ($kernel instanceof HttpKernel) {
            $kernel->appendMiddlewareToGroup('web', InertiaMiddleware::class);
        }
    }
}
