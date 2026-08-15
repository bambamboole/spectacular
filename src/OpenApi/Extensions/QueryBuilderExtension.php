<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\Contracts\HasApiFilters;
use Bambamboole\Spectacular\Contracts\HasApiIncludes;
use Bambamboole\Spectacular\Contracts\HasApiSorts;
use Bambamboole\Spectacular\OpenApi\Filters\FilterKind;
use Bambamboole\Spectacular\OpenApi\Filters\FilterSchemaFactory;
use Bambamboole\Spectacular\QueryBuilder as SpectacularQueryBuilder;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\FilterOperator;
use Spatie\QueryBuilder\Filters\FiltersOperator;
use Spatie\QueryBuilder\Includes\IncludedRelationship;
use Throwable;

final class QueryBuilderExtension extends AbstractQueryBuilderExtension
{
    /** @var list<string> */
    private const QUERY_BUILDER_METHODS = [
        'allowedFilters',
        'allowedIncludes',
        'allowedSorts',
    ];

    private const SINGLE_RESULT_METHOD = 'apiFindOrFail';

    private ?FilterSchemaFactory $filterSchemas = null;

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $actionNode = $routeInfo->actionNode();

        if (! $actionNode instanceof FunctionLike) {
            return;
        }

        $singleResultSubjects = $this->singleResultSubjectCalls($actionNode);
        $apiModels = $this->apiDeclarationModelClasses($actionNode, $singleResultSubjects);
        $singleResultModels = $this->subjectModelClasses($singleResultSubjects);
        $apiIncludeNames = $this->apiIncludeNames($this->uniqueStrings([...$apiModels, ...$singleResultModels]));
        $apiSortNames = $this->apiSortNames($apiModels);
        $parameters = [];
        $includesDeclared = false;
        $sortsDeclared = false;

        foreach ($apiModels as $model) {
            $parameters = [...$parameters, ...$this->apiFilterParameters($model)];
        }

        foreach ($this->queryBuilderCalls($actionNode, self::QUERY_BUILDER_METHODS) as $call) {
            $method = $this->methodName($call->name);
            $includesDeclared = $includesDeclared || $method === 'allowedIncludes';
            $sortsDeclared = $sortsDeclared || $method === 'allowedSorts';
            $parameters = [
                ...$parameters,
                ...match ($method) {
                    'allowedFilters' => $this->filterParameters($call),
                    'allowedIncludes' => $this->includeParameters($call, $apiIncludeNames),
                    'allowedSorts' => $this->sortParameters($call, $apiSortNames),
                    default => [],
                },
            ];
        }

        if (! $includesDeclared && ($includeParameter = $this->includeParameter($apiIncludeNames)) !== null) {
            $parameters[] = $includeParameter;
        }

        if (! $sortsDeclared && ($sortParameter = $this->sortParameter($apiSortNames)) !== null) {
            $parameters[] = $sortParameter;
        }

        $this->applyParameters($operation, $parameters);
    }

    /**
     * @return list<Parameter>
     */
    private function filterParameters(Expr\MethodCall $call): array
    {
        $model = $this->subjectModelClass($call->var);

        return array_map(
            function (array $declaration) use ($model): Parameter {
                ['name' => $name, 'factory' => $factory] = $declaration;

                return $this->filterParameter($model, $name, FilterKind::tryFromFactory($factory));
            },
            $this->argumentDeclarations($call->args, AllowedFilter::class, 'trashed'),
        );
    }

    private function filterParameter(?string $model, string $name, FilterKind $kind, ?FilterOperator $operator = null): Parameter
    {
        return Parameter::make($this->nestedParameterName('filter', $name), 'query')
            ->description($this->filterDescription($model, $name, $kind, $operator))
            ->setSchema(Schema::fromType($this->filterSchemas()->make($model, $name, $kind, $operator)));
    }

    /**
     * The models whose API declarations are in effect at runtime: only a chain
     * opened with the spectacular query builder auto-allows them, so a plain
     * spatie chain must not document filters its endpoint would reject. A
     * chain completed with apiFindOrFail() only accepts includes, so its
     * subject must not document filters and sorts the endpoint would reject.
     *
     * @param  list<Expr\StaticCall>  $singleResultSubjects
     * @return list<class-string>
     */
    private function apiDeclarationModelClasses(FunctionLike $actionNode, array $singleResultSubjects): array
    {
        $models = [];

        foreach ((new NodeFinder)->findInstanceOf($actionNode, Expr\StaticCall::class) as $call) {
            if (! $call->class instanceof Name || $this->methodName($call->name) !== 'for') {
                continue;
            }

            if (in_array($call, $singleResultSubjects, true)) {
                continue;
            }

            $builder = $this->resolvedClassName($call->class);

            if (! class_exists($builder) || ! is_a($builder, SpectacularQueryBuilder::class, true)) {
                continue;
            }

            $model = $this->subjectModelClass($call);

            if ($model !== null && ! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * @return list<Expr\StaticCall>
     */
    private function singleResultSubjectCalls(FunctionLike $actionNode): array
    {
        $subjects = [];

        foreach ((new NodeFinder)->findInstanceOf($actionNode, Expr\MethodCall::class) as $call) {
            if ($this->methodName($call->name) !== self::SINGLE_RESULT_METHOD) {
                continue;
            }

            $subject = $this->subjectCall($call->var);

            if ($subject !== null && ! in_array($subject, $subjects, true)) {
                $subjects[] = $subject;
            }
        }

        return $subjects;
    }

    /**
     * @param  list<Expr\StaticCall>  $subjects
     * @return list<class-string>
     */
    private function subjectModelClasses(array $subjects): array
    {
        $models = [];

        foreach ($subjects as $subject) {
            $model = $this->subjectModelClass($subject);

            if ($model !== null && ! in_array($model, $models, true)) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * @param  class-string  $model
     * @return list<Parameter>
     */
    private function apiFilterParameters(string $model): array
    {
        if (! is_a($model, HasApiFilters::class, true)) {
            return [];
        }

        try {
            $filters = $model::getApiFilters();
        } catch (Throwable) {
            return [];
        }

        return array_map(
            fn (AllowedFilter $filter): Parameter => $this->filterParameter(
                $model,
                $filter->getName(),
                FilterKind::fromAllowedFilter($filter),
                $this->filterOperator($filter),
            ),
            $filters,
        );
    }

    /**
     * @param  list<class-string>  $models
     * @return list<string>
     */
    private function apiIncludeNames(array $models): array
    {
        $names = [];

        foreach ($models as $model) {
            if (! is_a($model, HasApiIncludes::class, true)) {
                continue;
            }

            try {
                $includes = $model::getApiIncludes();
            } catch (Throwable) {
                continue;
            }

            foreach ($includes as $include) {
                if ($include instanceof AllowedInclude) {
                    $names[] = $include->getName();
                } elseif (is_string($include)) {
                    $names = [...$names, ...$this->expandedStringIncludeNames($include)];
                }
            }
        }

        return $this->uniqueStrings($names);
    }

    /**
     * @param  list<class-string>  $models
     * @return list<string>
     */
    private function apiSortNames(array $models): array
    {
        $names = [];

        foreach ($models as $model) {
            if (! is_a($model, HasApiSorts::class, true)) {
                continue;
            }

            try {
                $sorts = $model::getApiSorts();
            } catch (Throwable) {
                continue;
            }

            foreach ($sorts as $sort) {
                if ($sort instanceof AllowedSort) {
                    $names[] = $sort->getName();
                } elseif (is_string($sort)) {
                    $names[] = ltrim($sort, '-');
                }
            }
        }

        return $this->uniqueStrings($names);
    }

    private function filterDescription(?string $model, string $name, FilterKind $kind, ?FilterOperator $operator = null): string
    {
        if ($kind === FilterKind::Scope && $model !== null && ($summary = $this->scopeSummary($model, $name)) !== null) {
            return $summary;
        }

        if ($operator === FilterOperator::DYNAMIC) {
            return "Filter by `{$name}`. Prefix the value with `>`, `>=`, `<`, `<=`, or `<>` to choose the comparison; without a prefix the value must match exactly.";
        }

        return implode(' ', array_filter(["Filter by `{$name}`.", $kind->matching()]));
    }

    /**
     * The operator an operator filter compares with only lives in a protected
     * property of its runtime filter instance.
     */
    private function filterOperator(AllowedFilter $filter): ?FilterOperator
    {
        $filterClass = $filter->getFilterClass();

        if (! $filterClass instanceof FiltersOperator) {
            return null;
        }

        $operator = new ReflectionProperty(FiltersOperator::class, 'filterOperator')->getValue($filterClass);

        return $operator instanceof FilterOperator ? $operator : null;
    }

    /**
     * A scope filter points at a method the developer already documented, so its
     * docblock summary beats the generic template.
     *
     * @param  class-string  $model
     */
    private function scopeSummary(string $model, string $name): ?string
    {
        $method = $this->scopeMethod($model, $name);
        $docComment = $method?->getDocComment();

        if ($docComment === null || $docComment === false) {
            return null;
        }

        $body = preg_replace(['#^\s*/\*\*+#', '#\*+/\s*$#'], '', $docComment) ?? '';
        $lines = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('#^\s*\*+#', '', $line) ?? '');

            if ($line === '' || str_starts_with($line, '@')) {
                if ($lines !== []) {
                    break;
                }

                if (str_starts_with($line, '@')) {
                    return null;
                }

                continue;
            }

            $lines[] = $line;
        }

        return $lines === [] ? null : implode(' ', $lines);
    }

    /**
     * Laravel resolves both a #[Scope]-attributed method named after the filter
     * and the legacy scope-prefixed naming; method_exists sees protected, trait,
     * and inherited methods alike.
     *
     * @param  class-string  $model
     */
    private function scopeMethod(string $model, string $name): ?ReflectionMethod
    {
        $scope = Str::camel($name);

        foreach ([$scope, 'scope'.ucfirst($scope)] as $candidate) {
            if (method_exists($model, $candidate)) {
                return new ReflectionMethod($model, $candidate);
            }
        }

        return null;
    }

    private function filterSchemas(): FilterSchemaFactory
    {
        return $this->filterSchemas ??= new FilterSchemaFactory;
    }

    /**
     * @param  list<string>  $apiSortNames
     * @return list<Parameter>
     */
    private function sortParameters(Expr\MethodCall $call, array $apiSortNames): array
    {
        $sorts = array_map(
            fn (string $sort): string => ltrim($sort, '-'),
            $this->argumentNames($call->args, AllowedSort::class),
        );
        $parameter = $this->sortParameter([...$sorts, ...$apiSortNames]);

        return $parameter === null ? [] : [$parameter];
    }

    /**
     * @param  list<string>  $sorts
     */
    private function sortParameter(array $sorts): ?Parameter
    {
        $sorts = $this->uniqueStrings($sorts);

        if ($sorts === []) {
            return null;
        }

        $values = [];

        foreach ($sorts as $sort) {
            $values[] = $sort;
            $values[] = "-{$sort}";
        }

        return $this->arrayParameter(
            $this->parameterName('sort'),
            $values,
            sprintf(
                'Available sorts are %s. You can sort by multiple options by separating them with a comma. To sort in descending order, use `-` sign in front of the sort, for example: `-%s`.',
                $this->markdownValueList($sorts),
                $sorts[0],
            ),
        );
    }

    /**
     * @param  list<string>  $apiIncludeNames
     * @return list<Parameter>
     */
    private function includeParameters(Expr\MethodCall $call, array $apiIncludeNames): array
    {
        $includes = [];

        foreach ($call->args as $argument) {
            $includes = [
                ...$includes,
                ...$this->includeArgumentNames($argument->value),
            ];
        }

        $parameter = $this->includeParameter([...$includes, ...$apiIncludeNames]);

        return $parameter === null ? [] : [$parameter];
    }

    /**
     * @param  list<string>  $includes
     */
    private function includeParameter(array $includes): ?Parameter
    {
        $includes = $this->uniqueStrings($includes);

        if ($includes === []) {
            return null;
        }

        return $this->arrayParameter(
            $this->parameterName('include'),
            $includes,
            $this->availableValuesDescription('includes', $includes),
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function arrayParameter(string $name, array $values, string $description): Parameter
    {
        $items = (new StringType)->enum($values);

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
        return $this->uniqueStrings(array_column(
            $this->argumentDeclarations($arguments, $allowedClass, $defaultFactoryName),
            'name',
        ));
    }

    /**
     * @param  list<Arg>  $arguments
     * @param  class-string  $allowedClass
     * @return list<array{name: string, factory: string|null}>
     */
    private function argumentDeclarations(array $arguments, string $allowedClass, ?string $defaultFactoryName = null): array
    {
        $declarations = [];

        foreach ($arguments as $argument) {
            $declarations = [
                ...$declarations,
                ...$this->argumentExpressionDeclarations($argument->value, $allowedClass, $defaultFactoryName),
            ];
        }

        return $this->uniqueDeclarations($declarations);
    }

    /**
     * @param  class-string  $allowedClass
     * @return list<array{name: string, factory: string|null}>
     */
    private function argumentExpressionDeclarations(Expr $expression, string $allowedClass, ?string $defaultFactoryName = null): array
    {
        if ($expression instanceof String_) {
            return [['name' => $expression->value, 'factory' => null]];
        }

        if ($expression instanceof Expr\Array_) {
            $declarations = [];

            foreach ($expression->items as $item) {
                $declarations = [
                    ...$declarations,
                    ...$this->argumentExpressionDeclarations($item->value, $allowedClass, $defaultFactoryName),
                ];
            }

            return $declarations;
        }

        $declaration = $this->factoryDeclaration($expression, $allowedClass, $defaultFactoryName);

        return $declaration === null ? [] : [$declaration];
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

        $declaration = $this->factoryDeclaration($expression, AllowedInclude::class);

        return $declaration === null ? [] : [$declaration['name']];
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
     * @param  class-string  $allowedClass
     * @return array{name: string, factory: string|null}|null
     */
    private function factoryDeclaration(Expr $expression, string $allowedClass, ?string $defaultFactoryName = null): ?array
    {
        if ($expression instanceof Expr\MethodCall) {
            return $this->factoryDeclaration($expression->var, $allowedClass, $defaultFactoryName);
        }

        if (! $expression instanceof Expr\StaticCall || ! $this->isClassName($expression->class, $allowedClass)) {
            return null;
        }

        $factory = $this->methodName($expression->name);
        $name = $this->firstStringArgument($expression->args);

        if ($name !== null) {
            return ['name' => $name, 'factory' => $factory];
        }

        return $factory === $defaultFactoryName && $factory !== null
            ? ['name' => $factory, 'factory' => $factory]
            : null;
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
     * @param  list<array{name: string, factory: string|null}>  $declarations
     * @return list<array{name: string, factory: string|null}>
     */
    private function uniqueDeclarations(array $declarations): array
    {
        $unique = [];

        foreach ($declarations as $declaration) {
            $unique[$declaration['name']] ??= $declaration;
        }

        return array_values($unique);
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
            array_map(fn (string $value): string => "`{$value}`", $values),
        );
    }
}
