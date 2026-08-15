<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Bambamboole\Spectacular\Attributes\SpecOneOf;
use Bambamboole\Spectacular\Attributes\SpecProperty;
use Bambamboole\Spectacular\OpenApi\Types\OneOf;
use Brick\Math\BigDecimal;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\NullType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use ReflectionClass;
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
        return RequestBodyObject::make()
            ->setContent('application/json', $this->reference($dataClass, $components))
            ->required(true);
    }

    /**
     * Restates which properties are mandatory and turns every nested data class
     * into its own referenced component schema. The rules-derived inline shape
     * cannot get either right: a property with a default or a nullable type is
     * optional even though its rules say `required` when present, and a data
     * class nesting itself has no finite inline expansion.
     *
     * @param  class-string<Data>  $dataClass
     */
    public function refineSchema(ObjectType $type, string $dataClass, Components $components): void
    {
        $type->setRequired(array_values(array_intersect(
            DataObjects::requiredInputNames($dataClass),
            array_keys($type->properties),
        )));

        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            $name = $property->inputMappedName ?? $property->name;
            $node = $type->properties[$name] ?? null;

            if ($node === null) {
                continue;
            }

            if (! $node instanceof AnyOf && $this->acceptsBigDecimal($property)) {
                $type->properties[$name] = $this->bigDecimalSchema($property, $node);

                continue;
            }

            $nestedDataClass = $property->type->dataClass;

            if ($nestedDataClass === null || ! is_a($nestedDataClass, Data::class, true)) {
                continue;
            }

            if (($mapping = $this->oneOfMapping($nestedDataClass)) !== null) {
                $oneOf = $this->oneOfSchema($nestedDataClass, $mapping, $components);

                if ($property->type->kind->isDataCollectable() && $node instanceof ArrayType) {
                    $node->items = $oneOf;

                    continue;
                }

                if ($property->type->kind->isDataObject()) {
                    $oneOf->description = $node->description;
                    $oneOf->mergeExtensionProperties($node->extensionProperties());
                    $type->properties[$name] = $oneOf;
                    $this->dropDottedRuleProperties($type, $name);

                    continue;
                }
            }

            if ($property->type->kind->isDataCollectable() && $node instanceof ArrayType) {
                $node->items = $this->reference($nestedDataClass, $components);

                continue;
            }

            if ($property->type->kind->isDataObject()) {
                $reference = $this->reference($nestedDataClass, $components);
                $reference->setDescription($node->description);
                $reference->nullable($property->type->isNullable);
                $reference->mergeExtensionProperties($node->extensionProperties());

                $type->properties[$name] = $reference;
                $this->dropDottedRuleProperties($type, $name);
            }
        }
    }

    private function acceptsBigDecimal(DataProperty $property): bool
    {
        return class_exists(BigDecimal::class) && $property->type->acceptsType(BigDecimal::class);
    }

    /**
     * BigDecimalCast hydrates a BigDecimal property from an int, float, or
     * numeric-string payload value, and the `numeric` rule this property carries
     * would otherwise document `type: number` — rejecting the decimal strings
     * the API itself serializes. The anyOf states both accepted wire shapes.
     */
    private function bigDecimalSchema(DataProperty $property, Type $node): AnyOf
    {
        $number = new NumberType;
        $string = new StringType;
        $string->pattern = '^-?\d+(\.\d+)?$';

        if ($node instanceof NumberType) {
            $number->setMin($node->min)->setMax($node->max);
        }

        $items = [$number, $string];

        if ($property->type->isNullable) {
            $items[] = new NullType;
        }

        $schema = new AnyOf()->setItems($items);
        $schema->description = $node->description;
        $schema->mergeExtensionProperties($node->extensionProperties());

        return $schema;
    }

    /**
     * The full component schema of a data class, refined and with nested data
     * registered — the response-side counterpart of requestBody(). An abstract
     * property-morphable class resolves to the oneOf of its declared variants.
     *
     * @param  class-string<Data>  $dataClass
     */
    public function schemaType(string $dataClass, Components $components): Type
    {
        if (($mapping = $this->oneOfMapping($dataClass)) !== null) {
            return $this->oneOfSchema($dataClass, $mapping, $components);
        }

        $schema = Schema::createFromParameters($this->bodyParameters($dataClass));

        if ($schema->type instanceof ObjectType) {
            $this->refineSchema($schema->type, $dataClass, $components);
        }

        return $schema->type;
    }

    /**
     * @return array<string, class-string<Data>>|null
     */
    private function oneOfMapping(string $dataClass): ?array
    {
        $attributes = new ReflectionClass($dataClass)->getAttributes(SpecOneOf::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->mapping;
    }

    /**
     * @param  class-string<Data>  $abstractClass
     * @param  array<string, class-string<Data>>  $mapping
     */
    private function oneOfSchema(string $abstractClass, array $mapping, Components $components): OneOf
    {
        $discriminator = $this->morphPropertyName($abstractClass);
        $items = [];
        $references = [];

        foreach ($mapping as $value => $variantClass) {
            $items[] = $this->reference($variantClass, $components);
            $references[(string) $value] = '#/components/schemas/'.class_basename($variantClass);
            $this->pinDiscriminatorValue($components, $variantClass, $discriminator, (string) $value);
        }

        $oneOf = new OneOf()->setItems($items);

        if ($discriminator !== null) {
            $oneOf->setDiscriminator($discriminator, $references);
        }

        return $oneOf;
    }

    /**
     * @param  class-string<Data>  $dataClass
     */
    private function morphPropertyName(string $dataClass): ?string
    {
        foreach (app(DataConfig::class)->getDataClass($dataClass)->properties as $property) {
            if ($property->morphable) {
                return $property->inputMappedName ?? $property->name;
            }
        }

        return null;
    }

    /**
     * A variant documents its discriminator pinned to the single value that
     * selects it, while the abstract class keeps the full enum.
     *
     * @param  class-string<Data>  $variantClass
     */
    private function pinDiscriminatorValue(Components $components, string $variantClass, ?string $discriminator, string $value): void
    {
        if ($discriminator === null || ! $components->hasSchema(class_basename($variantClass))) {
            return;
        }

        $schema = $components->getSchema(class_basename($variantClass))->type;

        if (! $schema instanceof ObjectType || ! isset($schema->properties[$discriminator])) {
            return;
        }

        // The node must be replaced, not annotated: an enum-typed discriminator
        // documents as a $ref to the enum component, and a $ref overrides its
        // sibling keywords — the pinned enum would be ignored and every variant
        // would match every payload.
        $node = $schema->properties[$discriminator];
        $pinned = new StringType()->enum([$value]);
        $pinned->description = $node->description;

        $schema->properties[$discriminator] = $pinned;
    }

    /**
     * The rules of a nested data object also arrive as dot-notated keys
     * (`address.street`), which end up as flat sibling properties next to the
     * property they belong to. The referenced component schema documents those
     * fields, so the leftovers only duplicate it.
     */
    private function dropDottedRuleProperties(ObjectType $type, string $name): void
    {
        foreach (array_keys($type->properties) as $key) {
            if (str_starts_with((string) $key, "{$name}.")) {
                unset($type->properties[$key]);
            }
        }
    }

    /**
     * The schema is registered before it is refined, so a data class reachable
     * from itself resolves to the reference already being built instead of
     * recursing forever.
     *
     * @param  class-string<Data>  $dataClass
     */
    private function reference(string $dataClass, Components $components): Reference
    {
        $schemaName = class_basename($dataClass);

        if (! $components->hasSchema($schemaName)) {
            $schema = Schema::createFromParameters($this->bodyParameters($dataClass));
            $components->addSchema($schemaName, $schema);

            if ($schema->type instanceof ObjectType) {
                $this->refineSchema($schema->type, $dataClass, $components);
            }
        }

        return new Reference('schemas', $schemaName, $components);
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
                    : [$this->payloadForClass($nestedDataClass, $expanding)];

                continue;
            }

            if ($property->type->kind->isDataObject() && $nestedDataClass !== null && is_a($nestedDataClass, Data::class, true)) {
                $payload[$name] = isset($expanding[$nestedDataClass])
                    ? null
                    : $this->payloadForClass($nestedDataClass, $expanding);

                continue;
            }

            $payload[$name] = $property->hasDefaultValue ? $property->defaultValue : null;
        }

        return $payload;
    }

    /**
     * An abstract property-morphable class cannot answer for its own rules —
     * fabricating its first declared variant, discriminator included, lets
     * laravel-data resolve the morph and produce a complete rule set.
     *
     * @param  class-string<Data>  $dataClass
     * @param  array<class-string<Data>, true>  $expanding
     * @return array<string, mixed>
     */
    private function payloadForClass(string $dataClass, array $expanding): array
    {
        $mapping = $this->oneOfMapping($dataClass);

        if ($mapping === null) {
            return $this->payload($dataClass, $expanding);
        }

        $value = array_key_first($mapping);
        $payload = $this->payload($mapping[$value], $expanding);
        $discriminator = $this->morphPropertyName($dataClass);

        if ($discriminator !== null) {
            $payload[$discriminator] = $value;
        }

        return $payload;
    }
}
