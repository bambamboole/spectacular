<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

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

        config(['scramble.security_strategy' => MiddlewareAuthSecurityStrategy::class]);

        config()->set('auth.defaults.guard', 'workbench');
        config()->set('auth.guards.workbench', ['driver' => 'workbench-token']);

        config()->set('webhooks.scan_paths', [
            dirname(__DIR__, 2).'/app/Events',
        ]);
        $this->app->forgetInstance(WebhookEventRegistry::class);
    }

    public function boot(): void
    {
        Auth::viaRequest('workbench-token', function (Request $request): ?GenericUser {
            $token = (string) config('services.spectacular.demo_token', 'workbench-token');

            return $request->bearerToken() === $token
                ? new GenericUser(['id' => 1])
                : null;
        });
    }
}
