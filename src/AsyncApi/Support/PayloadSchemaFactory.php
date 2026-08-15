<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Support;

use BackedEnum;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\PhpDoc\PhpDocTypeWalker;
use Dedoc\Scramble\PhpDoc\ResolveFqnPhpDocTypeVisitor;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\TypeHelper;
use Dedoc\Scramble\Support\Type\Union;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use UnitEnum;

final class PayloadSchemaFactory
{
    use InvokesZeroArgMethods;

    private TypeTransformer $types;

    public function __construct()
    {
        $openApi = OpenApi::make('3.1.0')->setInfo(new InfoObject('AsyncAPI payloads'));
        $context = new OpenApiContext(
            $openApi,
            Scramble::getGeneratorConfig(Scramble::DEFAULT_API),
        );

        $this->types = app()->make(TypeTransformer::class, ['context' => $context]);
    }

    /**
     * @param  class-string  $eventClass
     * @return array<string, mixed>
     */
    public function forEvent(string $eventClass): array
    {
        $event = new ReflectionClass($eventClass);

        if ($event->hasMethod('broadcastWith')) {
            $schema = $this->schemaFromArrayReturn($event->getMethod('broadcastWith'));

            if ($schema !== null) {
                return $schema;
            }
        }

        return $this->schemaFromPublicProperties($event);
    }

    /**
     * @param  class-string  $class
     * @return array<string, mixed>
     */
    public function forMethod(string $class, string $methodName): array
    {
        $class = new ReflectionClass($class);

        if (! $class->hasMethod($methodName)) {
            return ['type' => 'object'];
        }

        return $this->schemaFromArrayReturn($class->getMethod($methodName)) ?? ['type' => 'object'];
    }

    /**
     * @param  class-string  $notificationClass
     * @return array<string, mixed>
     */
    public function forNotification(string $notificationClass): array
    {
        $notification = new ReflectionClass($notificationClass);

        if ($notification->hasMethod('broadcastWith')) {
            return $this->schemaFromArrayReturn($notification->getMethod('broadcastWith')) ?? ['type' => 'object'];
        }

        $schema = ['type' => 'object'];

        foreach (['toBroadcast', 'toArray'] as $methodName) {
            if (! $notification->hasMethod($methodName)) {
                continue;
            }

            $schema = $this->schemaFromArrayReturn($notification->getMethod($methodName)) ?? $schema;

            break;
        }

        return $this->withNotificationType($notification, $schema);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function schemaFromArrayReturn(ReflectionMethod $method): ?array
    {
        $type = $this->payloadType($method);

        if ($type instanceof KeyedArrayType && ! $type->isList) {
            return $this->schemaFromType($type);
        }

        if ($type instanceof ArrayType && ! $type->key instanceof IntegerType) {
            return $this->schemaFromType($type);
        }

        return $method->hasReturnType() ? ['type' => 'object'] : null;
    }

    private function payloadType(ReflectionMethod $method): ?Type
    {
        $typeNode = $this->returnTypeNode($method);

        if ($typeNode === null) {
            return null;
        }

        if ($typeNode instanceof IntersectionTypeNode) {
            foreach ($typeNode->types as $intersectionType) {
                if (! $intersectionType instanceof ObjectShapeNode) {
                    continue;
                }

                foreach ($intersectionType->items as $item) {
                    if ((string) $item->keyName === 'data') {
                        if ($resolver = $this->nameResolver($method)) {
                            PhpDocTypeWalker::traverse($item->valueType, [
                                new ResolveFqnPhpDocTypeVisitor($resolver),
                            ]);
                        }

                        return PhpDocTypeHelper::toType($item->valueType);
                    }
                }
            }
        }

        return PhpDocTypeHelper::toType($typeNode);
    }

    private function returnTypeNode(ReflectionMethod $method): ?TypeNode
    {
        $doc = $method->getDocComment();

        if ($doc === false) {
            return null;
        }

        try {
            $returnTag = array_values(PhpDoc::parse($doc, $this->nameResolver($method))->getReturnTagValues())[0] ?? null;

            return $returnTag?->type;
        } catch (Throwable) {
            return null;
        }
    }

    private function nameResolver(ReflectionMethod $method): ?FileNameResolver
    {
        $file = $method->getFileName();

        return is_string($file) ? FileNameResolver::createForFile($file) : null;
    }

    /**
     * @param  ReflectionClass<object>  $notification
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function withNotificationType(ReflectionClass $notification, array $schema): array
    {
        $broadcastType = $this->invokeZeroArgMethod($notification, 'broadcastType');
        $type = is_string($broadcastType) && $broadcastType !== '' ? $broadcastType : $notification->getName();

        $schema['type'] ??= 'object';
        $schema['properties'] ??= [];
        $schema['properties']['id'] ??= [
            'type' => 'string',
            'format' => 'uuid',
        ];
        $schema['properties']['type'] = [
            'type' => 'string',
            'enum' => [$type],
        ];

        $schema['required'] ??= [];

        if (! in_array('id', $schema['required'], true)) {
            $schema['required'][] = 'id';
        }

        if (! in_array('type', $schema['required'], true)) {
            $schema['required'][] = 'type';
        }

        return $schema;
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return array<string, mixed>
     */
    private function schemaFromPublicProperties(ReflectionClass $event): array
    {
        $properties = [];
        $required = [];

        foreach ($event->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $reflectionType = $property->getType();

            if ($property->isStatic() || $property->getName() === 'broadcastQueue') {
                continue;
            }

            $properties[$property->getName()] = $reflectionType === null
                ? []
                : $this->schemaFromType(TypeHelper::createTypeFromReflectionType($reflectionType));

            if (! $reflectionType?->allowsNull()) {
                $required[] = $property->getName();
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ], fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromType(Type $type): array
    {
        if ($type instanceof ArrayItemType_) {
            return $this->schemaFromType($type->value);
        }

        if ($type instanceof ObjectType) {
            return $this->schemaFromObjectType($type);
        }

        if ($type instanceof KeyedArrayType) {
            $schema = $this->types->transform($type)->toArray();

            foreach ($type->items as $item) {
                if ($item->key !== null) {
                    $schema['properties'][(string) $item->key] = $this->schemaFromType($item->value);
                }
            }

            return $schema;
        }

        if ($type instanceof ArrayType) {
            $valueSchema = $this->schemaFromType($type->value);

            return array_filter(
                $type->key instanceof IntegerType
                    ? ['type' => 'array', 'items' => $valueSchema]
                    : ['type' => 'object', 'additionalProperties' => $valueSchema],
                fn (mixed $value): bool => $value !== [],
            );
        }

        if ($type instanceof Union) {
            $nonNullTypes = array_values(array_filter(
                $type->types,
                fn (Type $member): bool => ! $member instanceof NullType,
            ));

            if (count($type->types) === 2 && count($nonNullTypes) === 1) {
                $schema = $this->schemaFromType($nonNullTypes[0]);

                return $schema === [] ? [] : $this->nullableSchema($schema);
            }

            return ['oneOf' => array_map($this->schemaFromType(...), $type->types)];
        }

        return (array) $this->types->transform($type)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromObjectType(ObjectType $type): array
    {
        if ($type->name === 'mixed') {
            return [];
        }

        if (is_a($type->name, DateTimeInterface::class, true)) {
            return [
                'type' => 'string',
                'format' => 'date-time',
                'x-php-type' => $type->name,
            ];
        }

        if (is_a($type->name, BigDecimal::class, true)) {
            return [
                'anyOf' => [
                    ['type' => 'number'],
                    ['type' => 'string', 'pattern' => '^-?\\d+(\\.\\d+)?$'],
                ],
                'x-php-type' => $type->name,
            ];
        }

        if (enum_exists($type->name)) {
            $values = array_map(
                fn (UnitEnum $case): string|int => $case instanceof BackedEnum ? $case->value : $case->name,
                $type->name::cases(),
            );

            return [
                'type' => is_int($values[0] ?? '') ? 'integer' : 'string',
                'enum' => $values,
                'x-php-type' => $type->name,
            ];
        }

        return [
            'type' => 'object',
            'x-php-type' => $type->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function nullableSchema(array $schema): array
    {
        if (array_key_exists('type', $schema) && is_string($schema['type'])) {
            return array_replace($schema, ['type' => [$schema['type'], 'null']]);
        }

        return [
            'oneOf' => [
                $schema,
                ['type' => 'null'],
            ],
        ];
    }
}
