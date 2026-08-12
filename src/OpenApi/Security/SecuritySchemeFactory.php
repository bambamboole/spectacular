<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Security;

use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\Oauth2SecurityScheme;
use Dedoc\Scramble\Support\Generator\SecuritySchemes\OAuthFlow;
use InvalidArgumentException;

/**
 * Builds the security schemes a document offers from plain configuration, so an
 * app declares how its API is authenticated instead of assembling scheme objects.
 */
final class SecuritySchemeFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(string $name, array $config): SecurityScheme
    {
        $type = $config['type'] ?? null;

        $scheme = match ($type) {
            'http' => SecurityScheme::http(
                (string) ($config['scheme'] ?? 'bearer'),
                (string) ($config['bearer_format'] ?? ''),
            ),
            'apiKey' => SecurityScheme::apiKey(
                (string) ($config['in'] ?? 'header'),
                (string) ($config['name'] ?? 'X-Api-Key'),
            ),
            'openIdConnect' => SecurityScheme::openIdConnect(self::url($config['url'] ?? throw new InvalidArgumentException(
                "Security scheme [{$name}] of type [openIdConnect] requires a `url`."
            ))),
            'oauth2' => self::oauth2($name, (array) ($config['flows'] ?? [])),
            'mutualTLS' => SecurityScheme::mutualTLS(),
            default => throw new InvalidArgumentException(
                "Security scheme [{$name}] has an unknown type [".(is_scalar($type) ? (string) $type : gettype($type)).'].'
            ),
        };

        $scheme->as($name);

        if (isset($config['description']) && is_string($config['description'])) {
            $scheme->setDescription($config['description']);
        }

        return $scheme;
    }

    /**
     * @param  array<string, mixed>  $flows
     */
    private static function oauth2(string $name, array $flows): Oauth2SecurityScheme
    {
        if ($flows === []) {
            throw new InvalidArgumentException("Security scheme [{$name}] of type [oauth2] requires at least one flow.");
        }

        $scheme = SecurityScheme::oauth2();

        foreach ($flows as $flowName => $flowConfig) {
            $flowConfig = (array) $flowConfig;

            $scheme->flow((string) $flowName, function (OAuthFlow $flow) use ($flowConfig): void {
                foreach (['authorization_url' => 'authorizationUrl', 'token_url' => 'tokenUrl', 'refresh_url' => 'refreshUrl'] as $key => $method) {
                    if (isset($flowConfig[$key])) {
                        $flow->{$method}(self::url($flowConfig[$key]));
                    }
                }

                foreach (self::scopes($flowConfig['scopes'] ?? []) as $scope => $description) {
                    $flow->addScope($scope, $description);
                }
            });
        }

        return $scheme;
    }

    /**
     * Scopes are often derived from the app itself (a permission catalog, an enum),
     * which a cached config file cannot hold — an invokable class-string is resolved
     * through the container instead.
     *
     * @return array<string, string>
     */
    private static function scopes(mixed $scopes): array
    {
        if (is_string($scopes) && class_exists($scopes)) {
            $scopes = app($scopes)();
        }

        return is_array($scopes) ? array_map(strval(...), $scopes) : [];
    }

    private static function url(mixed $url): string
    {
        $url = (string) (is_scalar($url) ? $url : '');

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
