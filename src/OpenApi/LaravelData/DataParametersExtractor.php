<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionProperty;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Documents the request body of a route action that takes a laravel-data object.
 * Scramble only understands validation it can see in the controller, so without
 * this such endpoints appear to accept nothing at all.
 */
final readonly class DataParametersExtractor implements ParameterExtractor
{
    public function __construct(private TypeTransformer $typeTransformer) {}

    /**
     * @param  ParametersExtractionResult[]  $parameterExtractionResults
     * @return ParametersExtractionResult[]
     */
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $dataClass = DataObjects::forRoute($routeInfo);

        if ($dataClass === null) {
            return $parameterExtractionResults;
        }

        $parameters = new RulesToParameters($this->validationRules($dataClass), [], $this->typeTransformer, 'body')
            ->mergeDotNotatedKeys(false)
            ->handle();

        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: $this->describe($dataClass, $parameters),
            schemaName: class_basename($dataClass),
            sourceClass: $dataClass,
        );

        return $parameterExtractionResults;
    }

    /**
     * @param  class-string<Data>  $dataClass
     * @param  list<Parameter>  $parameters
     * @return list<Parameter>
     */
    private function describe(string $dataClass, array $parameters): array
    {
        $properties = [];

        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            $properties[$property->inputMappedName ?? $property->name] = $property;
        }

        foreach ($parameters as $parameter) {
            $property = $properties[$parameter->name] ?? null;
            $description = $property === null ? null : $this->propertyDescription($property);

            if ($description !== null) {
                $parameter->description($description);
            }
        }

        return $parameters;
    }

    /**
     * Promoted constructor properties carry their own docblock, which reflection
     * exposes — so a payload field is described next to its declaration instead
     * of repeated in a per-endpoint attribute.
     */
    private function propertyDescription(DataProperty $property): ?string
    {
        if (! property_exists($property->className, $property->name)) {
            return null;
        }

        $docComment = new ReflectionProperty($property->className, $property->name)->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $body = preg_replace(['#^\s*/\*\*+#', '#\*+/\s*$#'], '', $docComment) ?? '';
        $lines = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('#^\s*\*+#', '', $line) ?? '');

            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines === [] ? null : implode(' ', $lines);
    }

    /**
     * @param  class-string<Data>  $dataClass
     * @return array<string, mixed>
     */
    private function validationRules(string $dataClass): array
    {
        $rules = [];

        foreach ($dataClass::getValidationRules($this->payload($dataClass)) as $field => $fieldRules) {
            $normalizedField = preg_replace('/\.\d+(?=\.|$)/', '.*', $field);
            $rules[$normalizedField ?? $field] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Rules are only generated for keys the payload contains, so every property
     * is fabricated to get a complete document.
     *
     * A Data class may nest itself (a tree payload such as a line item's
     * children), which has no finite expansion — without a guard the payload
     * recurses until the process dies. A class already on the stack stops at an
     * empty value, so its self-referencing branch is documented as an
     * unconstrained array rather than looping forever.
     *
     * @param  class-string<Data>  $dataClass
     * @param  array<class-string<Data>, true>  $expanding
     * @return array<string, mixed>
     */
    private function payload(string $dataClass, array $expanding = []): array
    {
        $payload = [];
        $expanding[$dataClass] = true;

        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            $name = $property->inputMappedName ?? $property->name;
            $nestedDataClass = $property->type->dataClass;

            if ($property->type->kind->isDataCollectable() && $nestedDataClass !== null && is_a($nestedDataClass, Data::class, true)) {
                $payload[$name] = isset($expanding[$nestedDataClass])
                    ? []
                    : [$this->payload($nestedDataClass, $expanding)];

                continue;
            }

            if ($property->type->kind->isDataObject() && $nestedDataClass !== null && is_a($nestedDataClass, Data::class, true)) {
                $payload[$name] = isset($expanding[$nestedDataClass])
                    ? null
                    : $this->payload($nestedDataClass, $expanding);

                continue;
            }

            $payload[$name] = $property->hasDefaultValue ? $property->defaultValue : null;
        }

        return $payload;
    }
}
