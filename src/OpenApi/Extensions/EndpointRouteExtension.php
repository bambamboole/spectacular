<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

final class EndpointRouteExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $operation->setAttribute('spectacularRoute', $routeInfo->route);
    }
}
