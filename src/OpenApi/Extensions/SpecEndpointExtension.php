<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\Attributes\SpecEndpoint;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

final class SpecEndpointExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $attribute = $routeInfo->reflectionAction()?->getAttributes(SpecEndpoint::class)[0] ?? null;

        if ($attribute === null) {
            return;
        }

        $endpoint = $attribute->newInstance();

        if ($endpoint->tooltip !== null) {
            $operation->setExtensionProperty('tooltip', $endpoint->tooltip);
        }

        if ($endpoint->internal) {
            $operation->setExtensionProperty('internal', true);
        }
    }
}
