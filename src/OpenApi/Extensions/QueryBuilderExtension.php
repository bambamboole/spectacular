<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Spatie\QueryBuilder\QueryBuilder;

final class QueryBuilderExtension extends AbstractQueryBuilderExtension
{
    /** @var list<string> */
    private const QUERY_BUILDER_METHODS = [
        'allowedFields',
        'allowedFilters',
        'allowedIncludes',
        'allowedSorts',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $actionNode = $routeInfo->actionNode();

        if (! $actionNode instanceof FunctionLike) {
            return;
        }

        $parameters = [];

        foreach ($this->queryBuilderCalls($actionNode, self::QUERY_BUILDER_METHODS) as $call) {
            $parameters = [
                ...$parameters,
                ...match ($this->methodName($call->name)) {
                    'allowedFields' => $this->fieldParameters($call),
                    'allowedFilters' => $this->filterParameters($call),
                    'allowedIncludes' => $this->includeParameters($call),
                    'allowedSorts' => $this->sortParameters($call),
                    default => [],
                },
            ];
        }

        $this->applyParameters($operation, $parameters);
    }

    /**
     * @return list<Parameter>
     */
    private function filterParameters(Expr\MethodCall $call): array
    {
        return array_map(
            fn (string $filter): Parameter => Parameter::make($this->nestedParameterName('filter', $filter), 'query')
                ->description("Filter by `{$filter}`.")
                ->setSchema(Schema::fromType(new StringType)),
            $this->argumentNames($call->args, AllowedFilter::class, 'trashed'),
        );
    }

    /**
     * @return list<Parameter>
     */
    private function sortParameters(Expr\MethodCall $call): array
    {
        $sorts = array_map(
            fn (string $sort): string => ltrim($sort, '-'),
            $this->argumentNames($call->args, AllowedSort::class),
        );
        $sorts = $this->uniqueStrings($sorts);

        if ($sorts === []) {
            return [];
        }

        $values = [];

        foreach ($sorts as $sort) {
            $values[] = $sort;
            $values[] = "-{$sort}";
        }

        return [$this->arrayParameter(
            $this->parameterName('sort'),
            $values,
            sprintf(
                'Available sorts are %s. You can sort by multiple options by separating them with a comma. To sort in descending order, use `-` sign in front of the sort, for example: `-%s`.',
                $this->markdownValueList($sorts),
                $sorts[0],
            ),
        )];
    }

    /**
     * @return list<Parameter>
     */
    private function includeParameters(Expr\MethodCall $call): array
    {
        $includes = [];

        foreach ($call->args as $argument) {
            $includes = [
                ...$includes,
                ...$this->includeArgumentNames($argument->value),
            ];
        }

        if ($includes === []) {
            return [];
        }

        $includes = $this->uniqueStrings($includes);

        return [$this->arrayParameter(
            $this->parameterName('include'),
            $includes,
            $this->availableValuesDescription('includes', $includes),
        )];
    }

    /**
     * @return list<Parameter>
     */
    private function fieldParameters(Expr\MethodCall $call): array
    {
        $fields = [];
        $defaultResource = $this->queryBuilderModelTable($call) ?? '_';

        foreach ($this->literalArgumentStrings($call->args) as $field) {
            [$resource, $name] = str_contains($field, '.')
                ? explode('.', $field, 2)
                : [$defaultResource, $field];

            $fields[$resource][] = $name;
        }

        $parameters = [];

        foreach ($fields as $resource => $resourceFields) {
            $parameters[] = $this->arrayParameter(
                $this->nestedParameterName('fields', $resource),
                $resourceFields,
                $this->availableValuesDescription('fields', $resourceFields),
            );
        }

        return $parameters;
    }

    /**
     * @param  list<string>  $values
     */
    private function arrayParameter(string $name, array $values, string $description): Parameter
    {
        $items = (new StringType)->enum($this->uniqueStrings($values));

        return Parameter::make($name, 'query')
            ->description($description)
            ->setStyle('form')
            ->setExplode(false)
            ->setSchema(Schema::fromType((new ArrayType)->setItems($items)));
    }

    /**
     * @param  list<Arg>  $arguments
     * @param  class-string  $allowedClass
     * @return list<string>
     */
    private function argumentNames(array $arguments, string $allowedClass, ?string $defaultFactoryName = null): array
    {
        $names = [];

        foreach ($arguments as $argument) {
            $names = [
                ...$names,
                ...$this->argumentExpressionNames($argument->value, $allowedClass, $defaultFactoryName),
            ];
        }

        return $this->uniqueStrings($names);
    }

    /**
     * @param  class-string  $allowedClass
     * @return list<string>
     */
    private function argumentExpressionNames(Expr $expression, string $allowedClass, ?string $defaultFactoryName = null): array
    {
        if ($expression instanceof String_) {
            return [$expression->value];
        }

        if ($expression instanceof Expr\Array_) {
            $names = [];

            foreach ($expression->items as $item) {
                $names = [
                    ...$names,
                    ...$this->argumentExpressionNames($item->value, $allowedClass, $defaultFactoryName),
                ];
            }

            return $names;
        }

        $factoryName = $this->factoryName($expression, $allowedClass, $defaultFactoryName);

        return $factoryName === null ? [] : [$factoryName];
    }

    /**
     * @return list<string>
     */
    private function includeArgumentNames(Expr $expression): array
    {
        if ($expression instanceof String_) {
            return $this->expandedStringIncludeNames($expression->value);
        }

        if ($expression instanceof Expr\Array_) {
            $names = [];

            foreach ($expression->items as $item) {
                $names = [
                    ...$names,
                    ...$this->includeArgumentNames($item->value),
                ];
            }

            return $names;
        }

        $factoryName = $this->factoryName($expression, AllowedInclude::class);

        return $factoryName === null ? [] : [$factoryName];
    }

    /**
     * @return list<string>
     */
    private function expandedStringIncludeNames(string $include): array
    {
        $countSuffix = (string) config('query-builder.suffixes.count', 'Count');
        $existsSuffix = (string) config('query-builder.suffixes.exists', 'Exists');

        if (str_ends_with($include, $countSuffix) || str_ends_with($include, $existsSuffix)) {
            return [$include];
        }

        $includes = [];

        foreach (IncludedRelationship::getIndividualRelationshipPathsFromInclude($include) as $path) {
            $includes[] = $path;

            if (! str_contains($path, '.')) {
                $includes[] = "{$path}{$countSuffix}";
                $includes[] = "{$path}{$existsSuffix}";
            }
        }

        return $includes;
    }

    /**
     * @param  list<Arg>  $arguments
     * @return list<string>
     */
    private function literalArgumentStrings(array $arguments): array
    {
        $values = [];

        foreach ($arguments as $argument) {
            $values = [
                ...$values,
                ...$this->literalExpressionStrings($argument->value),
            ];
        }

        return $this->uniqueStrings($values);
    }

    /**
     * @return list<string>
     */
    private function literalExpressionStrings(Expr $expression): array
    {
        if ($expression instanceof String_) {
            return [$expression->value];
        }

        if ($expression instanceof Expr\Array_) {
            $values = [];

            foreach ($expression->items as $item) {
                $values = [
                    ...$values,
                    ...$this->literalExpressionStrings($item->value),
                ];
            }

            return $values;
        }

        return [];
    }

    /**
     * @param  class-string  $allowedClass
     */
    private function factoryName(Expr $expression, string $allowedClass, ?string $defaultFactoryName = null): ?string
    {
        if ($expression instanceof Expr\MethodCall) {
            return $this->factoryName($expression->var, $allowedClass, $defaultFactoryName);
        }

        if (! $expression instanceof Expr\StaticCall || ! $this->isClassName($expression->class, $allowedClass)) {
            return null;
        }

        $name = $this->firstStringArgument($expression->args);

        if ($name !== null) {
            return $name;
        }

        $methodName = $this->methodName($expression->name);

        return $methodName === $defaultFactoryName ? $defaultFactoryName : null;
    }

    /**
     * @param  list<Arg>  $arguments
     */
    private function firstStringArgument(array $arguments): ?string
    {
        foreach ($arguments as $argument) {
            if ($argument->value instanceof String_) {
                return $argument->value->value;
            }
        }

        return null;
    }

    private function queryBuilderModelTable(Expr\MethodCall $call): ?string
    {
        $expression = $call->var;

        while ($expression instanceof Expr\MethodCall) {
            $expression = $expression->var;
        }

        if (! $expression instanceof Expr\StaticCall
            || $this->methodName($expression->name) !== 'for'
            || ! $this->isClassName($expression->class, QueryBuilder::class)
        ) {
            return null;
        }

        $modelClass = $this->classStringArgument($expression->args);

        if ($modelClass === null || ! class_exists($modelClass)) {
            return null;
        }

        $model = new $modelClass;

        return $model instanceof Model ? $model->getTable() : null;
    }

    /**
     * @param  list<Arg>  $arguments
     */
    private function classStringArgument(array $arguments): ?string
    {
        foreach ($arguments as $argument) {
            if (! $argument->value instanceof Expr\ClassConstFetch
                || ! $argument->value->name instanceof Identifier
                || $argument->value->name->name !== 'class'
                || ! $argument->value->class instanceof Name
            ) {
                continue;
            }

            return $this->resolvedClassName($argument->value->class);
        }

        return null;
    }

    private function parameterName(string $type): string
    {
        return (string) config("query-builder.parameters.{$type}", $type);
    }

    private function nestedParameterName(string $type, string $name): string
    {
        return sprintf('%s[%s]', $this->parameterName($type), $name);
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique($values));
    }

    /**
     * @param  list<string>  $values
     */
    private function availableValuesDescription(string $type, array $values): string
    {
        return sprintf(
            'Available %s are %s. You can include multiple options by separating them with a comma.',
            $type,
            $this->markdownValueList($values),
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function markdownValueList(array $values): string
    {
        return implode(
            ', ',
            array_map(
                fn (string $value): string => "`{$value}`",
                $this->uniqueStrings($values),
            ),
        );
    }
}
