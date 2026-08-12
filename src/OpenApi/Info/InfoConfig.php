<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Info;

final class InfoConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $values = config('spectacular.openapi.info', []);

        return is_array($values) ? array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []) : [];
    }

    public static function string(string $key): ?string
    {
        $value = self::values()[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public static function strings(string $key, array $keys): array
    {
        $values = self::values()[$key] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($keys as $name) {
            $value = $values[$name] ?? null;

            if (is_string($value) && $value !== '') {
                $strings[$name] = $value;
            }
        }

        return $strings;
    }

    /**
     * A licence is only a licence once it is named, and OpenAPI allows an SPDX
     * identifier or a URL but not both.
     *
     * @return array<string, string>
     */
    public static function license(): array
    {
        $license = self::strings('license', ['name', 'identifier', 'url']);

        if (! array_key_exists('name', $license)) {
            return [];
        }

        if (array_key_exists('identifier', $license)) {
            unset($license['url']);
        }

        return $license;
    }
}
