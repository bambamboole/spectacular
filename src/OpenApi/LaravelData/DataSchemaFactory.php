<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Bambamboole\Spectacular\Attributes\SpecProperty;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use ReflectionProperty;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Turns a laravel-data class into described body parameters and, on demand, a
 * complete request body schema. DataParametersExtractor uses it for Data
 * objects in controller signatures; StateTransitionOperations for Data
 * objects that only appear in transition constructors.
 */
final readonly class DataSchemaFactory
{
    public function __construct(private TypeTransformer $typeTransformer) {}

    /**
     * @param  class-string<Data>  $dataClass
     * @return list<Parameter>
     */
    public function bodyParameters(string $dataClass): array
    {
        $parameters = new RulesToParameters($this->validationRules($dataClass), [], $this->typeTransformer, 'body')
            ->mergeDotNotatedKeys(false)
            ->handle();

        return $this->describe($dataClass, $parameters);
    }

    /**
     * @param  class-string<Data>  $dataClass
     */
    public function requestBody(string $dataClass, Components $components): RequestBodyObject
    {
        $schemaName = class_basename($dataClass);

        if (! $components->hasSchema($schemaName)) {
            $schema = Schema::createFromParameters($this->bodyParameters($dataClass));

            if ($schema->type instanceof ObjectType) {
                $schema->type->setRequired(array_values(array_intersect(
                    DataObjects::requiredInputNames($dataClass),
                    array_keys($schema->type->properties),
                )));
            }

            $components->addSchema($schemaName, $schema);
        }

        return RequestBodyObject::make()
            ->setContent('application/json', new Reference('schemas', $schemaName, $components))
            ->required(true);
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

            if ($property === null) {
                continue;
            }

            $attribute = $property->attributes->first(SpecProperty::class);
            $description = $attribute->description ?? $this->propertyDescription($property);

            if ($description !== null) {
                $parameter->description($description);
            }

            if ($attribute?->tooltip !== null) {
                $this->addTooltip($parameter, $attribute->tooltip);
            }
        }

        return $parameters;
    }

    /**
     * A body parameter ends up as a request body schema property, and Scramble copies
     * only the description over — the tooltip has to be on the type to survive that.
     * A data object documenting a GET endpoint keeps its parameters instead, where the
     * tooltip belongs next to the parameter's own description.
     */
    private function addTooltip(Parameter $parameter, string $tooltip): void
    {
        $parameter->setExtensionProperty('tooltip', $tooltip);
        $parameter->schema?->type->setExtensionProperty('tooltip', $tooltip);
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
