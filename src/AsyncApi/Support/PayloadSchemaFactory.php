<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\AsyncApi\Support;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use UnitEnum;

final class PayloadSchemaFactory
{
    /**
     * @param  class-string  $eventClass
     * @return array<string, mixed>
     */
    public function forEvent(string $eventClass): array
    {
        $event = new ReflectionClass($eventClass);

        if ($event->hasMethod('broadcastWith')) {
            $schema = $this->schemaFromBroadcastWith($event);

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

        return $this->schemaFromArrayReturn($class, $class->getMethod($methodName)) ?? ['type' => 'object'];
    }

    /**
     * @param  class-string  $notificationClass
     * @param  class-string|null  $notifiableClass
     * @return array<string, mixed>
     */
    public function forNotification(string $notificationClass, ?string $notifiableClass = null): array
    {
        $notification = new ReflectionClass($notificationClass);

        if ($notification->hasMethod('broadcastWith')) {
            return $this->schemaFromArrayReturn($notification, $notification->getMethod('broadcastWith')) ?? ['type' => 'object'];
        }

        $schema = ['type' => 'object'];

        foreach (['toBroadcast', 'toArray'] as $methodName) {
            if (! $notification->hasMethod($methodName)) {
                continue;
            }

            $schema = $this->schemaFromArrayReturn($notification, $notification->getMethod($methodName)) ?? $schema;

            break;
        }

        return $this->withNotificationType($notification, $schema);
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return array<string, mixed>|null
     */
    private function schemaFromBroadcastWith(ReflectionClass $event): ?array
    {
        return $this->schemaFromArrayReturn($event, $event->getMethod('broadcastWith'));
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return array<string, mixed>|null
     */
    private function schemaFromArrayReturn(ReflectionClass $class, ReflectionMethod $method): ?array
    {
        $doc = $method->getDocComment();

        if ($doc === false) {
            return $method->hasReturnType() ? ['type' => 'object'] : null;
        }

        if (! preg_match('/@return\s+([^\n]+)/', $doc, $matches)) {
            return null;
        }

        $returnType = trim(str_replace('*/', '', $matches[1]));

        if (str_starts_with($returnType, 'array{') && str_ends_with($returnType, '}')) {
            return $this->schemaFromArrayShape($class, substr($returnType, 6, -1)) ?? ['type' => 'object'];
        }

        if (preg_match('/^array<string,\s*(.+)>$/', $returnType, $matches)) {
            return [
                'type' => 'object',
                'additionalProperties' => $this->schemaFromDocType($matches[1], $class),
            ];
        }

        if (preg_match('/object\s*\{\s*data\s*:\s*array\s*\{(.+)\}\s*\}/', $returnType, $matches)) {
            return $this->schemaFromArrayShape($class, $matches[1]) ?? ['type' => 'object'];
        }

        return ['type' => 'object'];
    }

    /**
     * @param  ReflectionClass<object>  $notification
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function withNotificationType(ReflectionClass $notification, array $schema): array
    {
        $type = $notification->getName();

        if ($notification->hasMethod('broadcastType')) {
            $method = $notification->getMethod('broadcastType');

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                try {
                    $broadcastType = $method->invoke($notification->newInstanceWithoutConstructor());

                    if (is_string($broadcastType) && $broadcastType !== '') {
                        $type = $broadcastType;
                    }
                } catch (Throwable) {
                    $type = $notification->getName();
                }
            }
        }

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
     * @return array<string, mixed>|null
     */
    private function schemaFromArrayShape(ReflectionClass $event, string $shape): ?array
    {
        $properties = [];
        $required = [];

        foreach ($this->splitTopLevel($shape) as $entry) {
            $parts = array_map(trim(...), explode(':', $entry, 2));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                return null;
            }

            [$key, $type] = $parts;
            $isOptional = str_ends_with($key, '?');
            $key = rtrim($key, '?');
            $properties[$key] = $this->schemaFromDocType($type, $event);

            if (! $isOptional) {
                $required[] = $key;
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ], fn (mixed $value): bool => $value !== []);
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
            if ($property->isStatic() || $property->getName() === 'broadcastQueue') {
                continue;
            }

            $properties[$property->getName()] = $this->schemaFromReflectionType($property->getType());

            if (! $property->getType()?->allowsNull()) {
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
    private function schemaFromReflectionType(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            $schemas = collect($type->getTypes())
                ->map(fn (ReflectionNamedType $namedType): array => $this->schemaFromNamedType($namedType->getName()))
                ->all();

            if ($this->isNullableUnion($type) && count($schemas) === 2) {
                $nonNullSchema = collect($schemas)
                    ->first(fn (array $schema): bool => $schema !== ['type' => 'null']);

                return $this->nullableSchema($nonNullSchema ?? []);
            }

            return ['oneOf' => $schemas];
        }

        if ($type instanceof ReflectionNamedType) {
            $schema = $this->schemaFromNamedType($type->getName());

            return $type->allowsNull() && $type->getName() !== 'mixed'
                ? $this->nullableSchema($schema)
                : $schema;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromNamedType(string $type): array
    {
        return match ($type) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'string' => ['type' => 'string'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            'mixed' => [],
            'null' => ['type' => 'null'],
            default => $this->schemaFromClassType($type),
        };
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return array<string, mixed>
     */
    private function schemaFromDocType(string $type, ReflectionClass $event): array
    {
        $type = trim($type);

        if (str_starts_with($type, '?')) {
            return $this->nullableSchema($this->schemaFromDocType(substr($type, 1), $event));
        }

        if (str_contains($type, '|')) {
            $parts = array_map(trim(...), explode('|', $type));

            if (in_array('null', $parts, true) && count($parts) === 2) {
                $nonNullType = collect($parts)->first(fn (string $part): bool => $part !== 'null');

                return $this->nullableSchema($this->schemaFromDocType($nonNullType ?? 'mixed', $event));
            }

            return [
                'oneOf' => array_map(fn (string $part): array => $this->schemaFromDocType($part, $event), $parts),
            ];
        }

        if (preg_match('/^list<(.+)>$/', $type, $matches)) {
            return [
                'type' => 'array',
                'items' => $this->schemaFromDocType($matches[1], $event),
            ];
        }

        if (preg_match('/^array<int,\s*(.+)>$/', $type, $matches)) {
            return [
                'type' => 'array',
                'items' => $this->schemaFromDocType($matches[1], $event),
            ];
        }

        if (preg_match('/^array<string,\s*(.+)>$/', $type, $matches)) {
            return [
                'type' => 'object',
                'additionalProperties' => $this->schemaFromDocType($matches[1], $event),
            ];
        }

        return $this->schemaFromNamedType($this->resolveDocType($type, $event));
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaFromClassType(string $type): array
    {
        if (is_a($type, DateTimeInterface::class, true)) {
            return [
                'type' => 'string',
                'format' => 'date-time',
                'x-php-type' => $type,
            ];
        }

        if (enum_exists($type)) {
            $cases = $type::cases();
            $values = array_map(
                fn (UnitEnum $case): string|int => $case instanceof BackedEnum ? $case->value : $case->name,
                $cases,
            );

            return [
                'type' => is_int($values[0] ?? '') ? 'integer' : 'string',
                'enum' => $values,
                'x-php-type' => $type,
            ];
        }

        return [
            'type' => 'object',
            'x-php-type' => $type,
        ];
    }

    /**
     * @param  ReflectionClass<object>  $event
     */
    private function resolveDocType(string $type, ReflectionClass $event): string
    {
        $type = ltrim($type, '\\');

        if (in_array($type, ['int', 'float', 'string', 'bool', 'array', 'mixed', 'null'], true)) {
            return $type;
        }

        $uses = $this->useStatements($event);

        if (isset($uses[$type])) {
            return $uses[$type];
        }

        $sameNamespace = $event->getNamespaceName().'\\'.$type;

        if (class_exists($sameNamespace) || enum_exists($sameNamespace)) {
            return $sameNamespace;
        }

        return $type;
    }

    /**
     * @param  ReflectionClass<object>  $event
     * @return array<string, class-string>
     */
    private function useStatements(ReflectionClass $event): array
    {
        $file = $event->getFileName();

        if (! is_string($file)) {
            return [];
        }

        preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($file), $matches);

        return collect($matches[1])
            ->mapWithKeys(function (string $use): array {
                $parts = preg_split('/\s+as\s+/i', trim($use));
                $class = ltrim($parts[0], '\\');
                $alias = $parts[1] ?? class_basename($class);

                return [$alias => $class];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    private function splitTopLevel(string $value): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;

        foreach (str_split($value) as $character) {
            if (in_array($character, ['<', '{', '('], true)) {
                $depth++;
            }

            if (in_array($character, ['>', '}', ')'], true)) {
                $depth--;
            }

            if ($character === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $character;
        }

        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
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

    private function isNullableUnion(ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $namedType) {
            if ($namedType->getName() === 'null') {
                return true;
            }
        }

        return false;
    }
}
