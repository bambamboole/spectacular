<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Support\RouteInfo;
use ReflectionNamedType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataConfig;

final class DataObjects
{
    /**
     * A Data object with its own `rules()` method is left to Scramble, which
     * reads that method directly.
     *
     * @return class-string<Data>|null
     */
    public static function forRoute(RouteInfo $routeInfo): ?string
    {
        $action = $routeInfo->reflectionAction();

        if ($action === null) {
            return null;
        }

        foreach ($action->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (is_a($class, Data::class, true) && ! method_exists($class, 'rules')) {
                return $class;
            }
        }

        return null;
    }

    /**
     * The input names a client has to send. Validation rules cannot answer this:
     * they describe a field that is present, while a property carrying a default
     * or a nullable type may be left out entirely.
     *
     * @param  class-string<Data>  $dataClass
     * @return list<string>
     */
    public static function requiredInputNames(string $dataClass): array
    {
        $required = [];

        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            if ($property->type->isNullable || $property->type->isOptional || $property->hasDefaultValue) {
                continue;
            }

            $required[] = $property->inputMappedName ?? $property->name;
        }

        return $required;
    }
}
