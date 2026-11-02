<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\Attributes\SpecParameter;
use Dedoc\Scramble\Attributes\MissingValue;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
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
        $documented = false;

        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->name !== $attribute->name) {
                continue;
            }

            $documented = true;

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

        if (! $documented && ($property = $this->nestedFilterProperty($operation, $attribute->name)) !== null) {
            $this->documentProperty($property, $attribute);
        }
    }

    /**
     * Without flattened deep query parameters, `filter[status]` only exists as the
     * `status` property of the `filter` deepObject parameter.
     */
    private function nestedFilterProperty(Operation $operation, string $name): ?Type
    {
        $filterName = (string) config('query-builder.parameters.filter', 'filter');

        if (! preg_match('/^'.preg_quote($filterName, '/').'\[(.+)\]$/', $name, $matches)) {
            return null;
        }

        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter
                && $parameter->in === 'query'
                && $parameter->name === $filterName
                && $parameter->schema instanceof Schema
                && $parameter->schema->type instanceof ObjectType
                && $parameter->schema->type->hasProperty($matches[1])) {
                return $parameter->schema->type->getProperty($matches[1]);
            }
        }

        return null;
    }

    private function documentProperty(Type $property, SpecParameter $attribute): void
    {
        if ($attribute->description !== null) {
            $property->setDescription($attribute->description);
        }

        if ($attribute->tooltip !== null) {
            $property->setExtensionProperty('tooltip', $attribute->tooltip);
        }

        if (! $attribute->default instanceof MissingValue) {
            $property->default($attribute->default);
        }
    }
}
