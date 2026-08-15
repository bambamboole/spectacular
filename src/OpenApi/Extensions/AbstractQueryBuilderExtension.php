<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\QueryBuilder as SpectacularQueryBuilder;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

abstract class AbstractQueryBuilderExtension extends OperationExtension
{
    /**
     * @param  list<string>  $methods
     * @return list<Expr\MethodCall>
     */
    protected function queryBuilderCalls(FunctionLike $actionNode, array $methods): array
    {
        return array_values(array_filter(
            (new NodeFinder)->findInstanceOf($actionNode, Expr\MethodCall::class),
            fn (Expr\MethodCall $node): bool => $node->name instanceof Identifier
                && in_array($node->name->name, $methods, true)
                && $this->isQueryBuilderChain($node->var),
        ));
    }

    /**
     * @param  list<Parameter>  $parameters
     */
    protected function applyParameters(Operation $operation, array $parameters): void
    {
        $newParameters = [];

        foreach ($this->uniqueParameters($parameters) as $parameter) {
            if ($this->replaceOperationParameter($operation, $parameter)) {
                continue;
            }

            $newParameters[] = $parameter;
        }

        $operation->addParameters($newParameters);
    }

    protected function isQueryBuilderChain(Expr $expression): bool
    {
        return $this->subjectCall($expression) !== null;
    }

    /**
     * The class the chain was opened with, when `for()` names one statically —
     * `QueryBuilder::for(User::class)` filters users, and a builder subject
     * like `QueryBuilder::for(User::query()->whereHas(...))` still names its
     * model at the base of the method chain.
     *
     * @return class-string|null
     */
    protected function subjectModelClass(Expr $expression): ?string
    {
        $argument = $this->subjectCall($expression)?->args[0]->value ?? null;

        if ($argument instanceof Expr\ClassConstFetch
            && $argument->name instanceof Identifier
            && $argument->name->name === 'class'
            && $argument->class instanceof Name) {
            $class = $this->resolvedClassName($argument->class);

            return class_exists($class) ? $class : null;
        }

        if ($argument instanceof String_ && class_exists($argument->value)) {
            return $argument->value;
        }

        return $this->builderSubjectModelClass($argument);
    }

    /**
     * @return class-string|null
     */
    private function builderSubjectModelClass(?Expr $argument): ?string
    {
        while ($argument instanceof Expr\MethodCall) {
            $argument = $argument->var;
        }

        if (! $argument instanceof Expr\StaticCall || ! $argument->class instanceof Name) {
            return null;
        }

        $class = $this->resolvedClassName($argument->class);

        return class_exists($class) && is_a($class, Model::class, true) ? $class : null;
    }

    protected function subjectCall(Expr $expression): ?Expr\StaticCall
    {
        if ($expression instanceof Expr\MethodCall) {
            return $this->subjectCall($expression->var);
        }

        return $expression instanceof Expr\StaticCall
            && $this->methodName($expression->name) === 'for'
            && ($this->isClassName($expression->class, SpatieQueryBuilder::class)
                || $this->isClassName($expression->class, SpectacularQueryBuilder::class))
                ? $expression
                : null;
    }

    protected function isClassName(Name|Expr $class, string $expected): bool
    {
        if (! $class instanceof Name) {
            return false;
        }

        $className = $this->resolvedClassName($class);

        $expected = ltrim($expected, '\\');

        if ($className === $expected) {
            return true;
        }

        return ! str_contains($className, '\\') && $className === $this->baseClassName($expected);
    }

    protected function resolvedClassName(Name $class): string
    {
        return $class->getAttribute('resolvedName') instanceof Name
            ? $class->getAttribute('resolvedName')->toString()
            : $class->toString();
    }

    protected function methodName(Identifier|Name|Expr $name): ?string
    {
        if ($name instanceof Identifier) {
            return $name->name;
        }

        if ($name instanceof Name) {
            return $name->toString();
        }

        return null;
    }

    protected function baseClassName(string $className): string
    {
        $segments = explode('\\', $className);

        return end($segments) ?: $className;
    }

    /**
     * @param  list<Parameter>  $parameters
     * @return list<Parameter>
     */
    protected function uniqueParameters(array $parameters): array
    {
        $unique = [];

        foreach ($parameters as $parameter) {
            $unique["{$parameter->in}:{$parameter->name}"] = $parameter;
        }

        return array_values($unique);
    }

    protected function replaceOperationParameter(Operation $operation, Parameter $candidate): bool
    {
        foreach ($operation->parameters as $index => $parameter) {
            if ($parameter instanceof Parameter && $parameter->in === $candidate->in && $parameter->name === $candidate->name) {
                $operation->parameters[$index] = $candidate;

                return true;
            }
        }

        return false;
    }
}
