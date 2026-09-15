<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

use InvalidArgumentException;

final readonly class Endpoint
{
    /** @param array<string, mixed> $operation */
    private function __construct(
        public string $target,
        public string $method,
        public array $operation,
        public bool $named,
    ) {
        if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'], true)) {
            throw new InvalidArgumentException("Unsupported HTTP method [$method].");
        }
    }

    /** @param array<string, mixed> $operation */
    public static function route(string $name, string $method, array $operation): self
    {
        return new self($name, strtolower($method), $operation, true);
    }

    /** @param array<string, mixed> $operation */
    public static function path(string $path, string $method, array $operation): self
    {
        return new self(trim($path, '/'), strtolower($method), $operation, false);
    }
}
