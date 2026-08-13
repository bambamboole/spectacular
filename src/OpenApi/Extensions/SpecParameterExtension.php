<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\Attributes\SpecParameter;
use Dedoc\Scramble\Attributes\MissingValue;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\RouteInfo;

final class SpecParameterExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($routeInfo->reflectionAction()?->getAttributes(SpecParameter::class) ?? [] as $attribute) {
            $this->document($operation, $attribute->newInstance());
        }
    }

    private function document(Operation $operation, SpecParameter $attribute): void
    {
        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->name !== $attribute->name) {
                continue;
            }

            if ($attribute->description !== null) {
                $parameter->description($attribute->description);
            }

            if ($attribute->tooltip !== null) {
                $parameter->setExtensionProperty('tooltip', $attribute->tooltip);
            }

            if (! $attribute->default instanceof MissingValue) {
                $parameter->schema?->type->default($attribute->default);
            }
        }
    }
}
