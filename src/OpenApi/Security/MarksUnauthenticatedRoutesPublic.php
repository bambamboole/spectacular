<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Security;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * The configured schemes apply to the whole document, so a route that carries no
 * authentication middleware has to opt out explicitly — an empty `security` is
 * how OpenAPI spells "no credentials needed".
 */
final readonly class MarksUnauthenticatedRoutesPublic implements OperationTransformer
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if ($operation->security !== null && $operation->security !== []) {
            return;
        }

        $patterns = SecurityConfig::middleware();

        $authenticated = collect($routeInfo->route->gatherMiddleware())
            ->some(fn (string $middleware): bool => Str::is($patterns, $middleware));

        if (! $authenticated) {
            $operation->security = [];
        }
    }
}
