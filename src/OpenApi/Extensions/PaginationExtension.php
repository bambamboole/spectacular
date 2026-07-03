<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

final class PaginationExtension extends AbstractQueryBuilderExtension
{
    /** @var list<string> */
    private const PAGINATION_METHODS = [
        'paginate',
        'simplePaginate',
        'cursorPaginate',
    ];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $actionNode = $routeInfo->actionNode();

        if (! $actionNode instanceof FunctionLike) {
            return;
        }

        $parameters = [];

        foreach ($this->queryBuilderCalls($actionNode, self::PAGINATION_METHODS) as $call) {
            $method = $this->methodName($call->name);

            $parameters[] = match ($method) {
                'cursorPaginate' => $this->cursorParameter($call),
                default => $this->pageParameter($call),
            };

            if ($perPageParameter = $this->perPageParameter($call)) {
                $parameters[] = $perPageParameter;
            }
        }

        $this->applyParameters($operation, $parameters);
    }

    private function pageParameter(Expr\MethodCall $call): Parameter
    {
        $name = $this->stringArgument($call->args, 2, 'pageName') ?? 'page';

        return $this->integerParameter($name, 'The page number to retrieve.');
    }

    private function cursorParameter(Expr\MethodCall $call): Parameter
    {
        $name = $this->stringArgument($call->args, 2, 'cursorName') ?? 'cursor';

        return Parameter::make($name, 'query')
            ->description('The cursor to start pagination from.')
            ->setSchema(Schema::fromType(new StringType));
    }

    private function perPageParameter(Expr\MethodCall $call): ?Parameter
    {
        $argument = $this->argument($call->args, 0, 'perPage');

        if (! $argument instanceof Expr\MethodCall) {
            return null;
        }

        $method = $this->methodName($argument->name);

        if (! in_array($method, ['integer', 'input', 'query'], true)) {
            return null;
        }

        $name = $this->stringArgument($argument->args, 0, 'key');

        if ($name === null) {
            return null;
        }

        return $this->integerParameter(
            $name,
            'The number of items to retrieve per page.',
            $this->integerArgument($argument->args, 1, 'default'),
        );
    }

    private function integerParameter(string $name, string $description, ?int $default = null): Parameter
    {
        $type = (new IntegerType)->setMin(1);

        if ($default !== null) {
            $type->default($default);
        }

        return Parameter::make($name, 'query')
            ->description($description)
            ->setSchema(Schema::fromType($type));
    }

    /**
     * @param  list<Arg>  $arguments
     */
    private function stringArgument(array $arguments, int $position, ?string $name = null): ?string
    {
        $argument = $this->argument($arguments, $position, $name);

        return $argument instanceof String_ ? $argument->value : null;
    }

    /**
     * @param  list<Arg>  $arguments
     */
    private function integerArgument(array $arguments, int $position, ?string $name = null): ?int
    {
        $argument = $this->argument($arguments, $position, $name);

        return $argument instanceof Int_ ? $argument->value : null;
    }

    /**
     * @param  list<Arg>  $arguments
     */
    private function argument(array $arguments, int $position, ?string $name = null): ?Expr
    {
        if ($name !== null) {
            foreach ($arguments as $argument) {
                if ($argument->name instanceof Identifier && $argument->name->name === $name) {
                    return $argument->value;
                }
            }
        }

        return $arguments[$position]->value ?? null;
    }
}
