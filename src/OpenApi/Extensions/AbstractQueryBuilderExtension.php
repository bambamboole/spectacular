<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use Spatie\QueryBuilder\QueryBuilder;

abstract class AbstractQueryBuilderExtension extends OperationExtension
{
    /**
     * @param  list<string>  $methods
     * @return list<Expr\MethodCall>
     */
    protected function queryBuilderCalls(FunctionLike $actionNode, array $methods): array
    {
        $nodes = (new NodeFinder)->find(
            $actionNode,
            fn (Node $node): bool => $node instanceof Expr\MethodCall
                && $node->name instanceof Identifier
                && in_array($node->name->name, $methods, true)
                && $this->isQueryBuilderChain($node->var),
        );

        $calls = [];

        foreach ($nodes as $node) {
            if ($node instanceof Expr\MethodCall) {
                $calls[] = $node;
            }
        }

        return $calls;
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
        if ($expression instanceof Expr\MethodCall) {
            return $this->isQueryBuilderChain($expression->var);
        }

        return $expression instanceof Expr\StaticCall
            && $this->methodName($expression->name) === 'for'
            && $this->isClassName($expression->class, QueryBuilder::class);
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
