<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Info;

use Dedoc\Scramble\Support\Generator\Contact;
use Dedoc\Scramble\Support\Generator\License;

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

    public static function contact(): ?Contact
    {
        $contact = self::strings('contact', ['name', 'url', 'email']);

        if ($contact === []) {
            return null;
        }

        return new Contact($contact['name'] ?? null, $contact['url'] ?? null, $contact['email'] ?? null);
    }

    /**
     * A licence is only a licence once it is named, and OpenAPI allows an SPDX
     * identifier or a URL but not both.
     */
    public static function license(): ?License
    {
        $license = self::strings('license', ['name', 'identifier', 'url']);

        if (! array_key_exists('name', $license)) {
            return null;
        }

        $identifier = $license['identifier'] ?? null;

        return new License($license['name'], $identifier, $identifier === null ? $license['url'] ?? null : null);
    }
}
