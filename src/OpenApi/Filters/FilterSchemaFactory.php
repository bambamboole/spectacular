<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Filters;

use Brick\Math\BigDecimal;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;
use Spatie\ModelStates\State;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Throwable;

/**
 * Types a query builder filter from the model it filters, so a client sees the values
 * a column accepts instead of a bare string.
 */
final class FilterSchemaFactory
{
    /** @var array<string, Model|null> */
    private array $models = [];

    public function make(?string $modelClass, string $name, FilterKind $kind, ?FilterOperator $operator = null, ?string $internalName = null): Type
    {
        if ($kind === FilterKind::Trashed) {
            return new StringType()->enum(['with', 'only', '']);
        }

        if (! $kind->comparesTypedValues()) {
            return new StringType;
        }

        $model = $this->model($modelClass);

        if ($model === null) {
            return new StringType;
        }

        if ($kind === FilterKind::BelongsTo && ($related = $this->relatedModelFromRelationPath($model, $internalName ?? $name)) !== null) {
            return $this->keyType($related);
        }

        $columnType = $this->columnType($model, $kind === FilterKind::Between ? ($internalName ?? $name) : $name);

        if ($operator === FilterOperator::DYNAMIC) {
            return $this->dynamicOperatorType($columnType);
        }

        return $columnType;
    }

    /**
     * A belongsTo filter names its relation in the internal name — dot-nested
     * for a relation reached through others, exactly how FiltersBelongsTo
     * resolves it at runtime. The name-based heuristic in relatedModel() stays
     * the fallback for filters that follow the `{relation}_id` convention.
     */
    private function relatedModelFromRelationPath(Model $model, string $path): ?Model
    {
        foreach (explode('.', $path) as $segment) {
            if ($segment === '' || ! method_exists($model, $segment)) {
                return null;
            }

            try {
                $relation = $model->{$segment}();
            } catch (Throwable) {
                return null;
            }

            if (! $relation instanceof Relation) {
                return null;
            }

            $model = $relation->getRelated();
        }

        return $model;
    }

    /**
     * A dynamic operator filter embeds the comparison in the value
     * (`filter[created_at]=>=2026-01-01`), so the typed column schema
     * (number, date-time format) would reject every prefixed value. The
     * column's value type survives as `x-value-format` for tooling.
     */
    private function dynamicOperatorType(Type $columnType): Type
    {
        $type = new StringType;

        $format = match (true) {
            $columnType->format !== '' => $columnType->format,
            $columnType->type !== 'string' => $columnType->type,
            default => null,
        };

        if ($format !== null) {
            $type->setExtensionProperty('value-format', $format);
        }

        return $type;
    }

    private function columnType(Model $model, string $name): Type
    {
        $cast = $model->getCasts()[$name] ?? null;

        if (is_string($cast) && ($type = $this->castType($cast)) !== null) {
            return $type;
        }

        // The timestamp columns are dates without appearing in the casts.
        if (in_array($name, $model->getDates(), true)) {
            return new StringType()->format('date-time');
        }

        if ($name === $model->getKeyName()) {
            return $this->keyType($model);
        }

        $related = $this->relatedModel($model, $name);

        return $related === null ? new StringType : $this->keyType($related);
    }

    private function castType(string $cast): ?Type
    {
        if (enum_exists($cast)) {
            return $this->enumType($cast);
        }

        if (class_exists(State::class) && is_a($cast, State::class, true)) {
            return new StringType()->enum($cast::getStateMapping()->keys()->all());
        }

        if ($this->hydratesBigDecimal($cast)) {
            return new NumberType;
        }

        return match (Str::before($cast, ':')) {
            'bool', 'boolean' => new BooleanType,
            'int', 'integer' => new IntegerType,
            'real', 'float', 'double', 'decimal' => new NumberType,
            'date' => new StringType()->format('date'),
            'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => new StringType()->format('date-time'),
            default => null,
        };
    }

    /**
     * A custom cast class whose get() hydrates a BigDecimal marks a decimal
     * column the model gives no scalar cast for.
     */
    private function hydratesBigDecimal(string $cast): bool
    {
        $class = Str::before($cast, ':');

        if (! class_exists(BigDecimal::class) || ! is_a($class, CastsAttributes::class, true)) {
            return false;
        }

        $returnType = new ReflectionMethod($class, 'get')->getReturnType();

        return $returnType instanceof ReflectionNamedType && is_a($returnType->getName(), BigDecimal::class, true);
    }

    /**
     * @param  class-string  $enum
     */
    private function enumType(string $enum): Type
    {
        $cases = $enum::cases();
        $backing = $cases[0]->value ?? null;

        if (! is_int($backing) && ! is_string($backing)) {
            return new StringType()->enum(array_column($cases, 'name'));
        }

        $type = is_int($backing) ? new IntegerType : new StringType;

        return $type->enum(array_column($cases, 'value'));
    }

    private function keyType(Model $model): Type
    {
        if (in_array(HasUuids::class, class_uses_recursive($model), true)) {
            return new StringType()->format('uuid');
        }

        return $model->getKeyType() === 'int' ? new IntegerType : new StringType;
    }

    private function relatedModel(Model $model, string $name): ?Model
    {
        foreach ([Str::beforeLast($name, '_id'), $name] as $candidate) {
            $related = $this->belongsTo($model, Str::camel($candidate));

            if ($related !== null) {
                return $related;
            }
        }

        return null;
    }

    /**
     * Only a relation that declares `BelongsTo` is called: the return type proves the
     * method builds a relation rather than doing work of its own.
     */
    private function belongsTo(Model $model, string $relation): ?Model
    {
        if ($relation === '' || ! method_exists($model, $relation)) {
            return null;
        }

        $returnType = new ReflectionMethod($model, $relation)->getReturnType();

        if (! $returnType instanceof ReflectionNamedType || $returnType->getName() !== BelongsTo::class) {
            return null;
        }

        try {
            return $model->{$relation}()->getRelated();
        } catch (Throwable) {
            return null;
        }
    }

    private function model(?string $class): ?Model
    {
        if ($class === null) {
            return null;
        }

        if (! array_key_exists($class, $this->models)) {
            $this->models[$class] = is_subclass_of($class, Model::class) ? new $class : null;
        }

        return $this->models[$class];
    }
}
