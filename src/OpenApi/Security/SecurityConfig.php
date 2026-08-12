<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Security;

final class SecurityConfig
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function schemes(): array
    {
        $schemes = config('spectacular.openapi.security.schemes', []);

        if (! is_array($schemes)) {
            return [];
        }

        $configured = [];

        foreach ($schemes as $name => $config) {
            if (is_string($name) && is_array($config)) {
                $configured[$name] = $config;
            }
        }

        return $configured;
    }

    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        $middleware = config('spectacular.openapi.security.middleware', ['auth', 'auth:*']);

        return is_array($middleware) ? array_values(array_filter($middleware, is_string(...))) : [];
    }
}
