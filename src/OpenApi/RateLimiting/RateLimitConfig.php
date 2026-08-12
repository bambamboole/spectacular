<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\RateLimiting;

final class RateLimitConfig
{
    /**
     * @return list<string>
     */
    public static function middleware(): array
    {
        return self::strings('middleware');
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return self::descriptions('headers');
    }

    /**
     * @return array<string, string>
     */
    public static function exhaustedHeaders(): array
    {
        return self::descriptions('exhausted_headers');
    }

    /**
     * @return list<string>
     */
    private static function strings(string $key): array
    {
        $values = config("spectacular.openapi.rate_limiting.{$key}", []);

        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }

    /**
     * @return array<string, string>
     */
    private static function descriptions(string $key): array
    {
        $values = config("spectacular.openapi.rate_limiting.{$key}", []);

        if (! is_array($values)) {
            return [];
        }

        $descriptions = [];

        foreach ($values as $name => $description) {
            if (is_string($name) && is_string($description)) {
                $descriptions[$name] = $description;
            }
        }

        return $descriptions;
    }
}
