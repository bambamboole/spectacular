<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\OpenApi\PaginationResponseType;
use Bambamboole\Spectacular\PaginationMode;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;
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
        'apiPaginate',
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

            if ($method === 'apiPaginate') {
                if ($pagination = $this->apiPagination($call)) {
                    array_push($parameters, ...$this->apiPaginationParameters(...$pagination));
                    $this->applyApiPaginationResponse($operation, $pagination['modes']);
                }

                continue;
            }

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

    /**
     * @return array{modes: non-empty-list<PaginationMode>, max: positive-int}|null
     */
    private function apiPagination(Expr\MethodCall $call): ?array
    {
        $modesArgument = $this->argument($call->args, 0, 'modes');
        $modes = $modesArgument === null
            ? [PaginationMode::Default]
            : $this->paginationModes($modesArgument);

        if ($modes === null) {
            return null;
        }

        $maxArgument = $this->argument($call->args, 1, 'max');
        $max = $maxArgument instanceof Int_ ? $maxArgument->value : 100;

        if (($maxArgument !== null && ! $maxArgument instanceof Int_) || $max < 1) {
            return null;
        }

        return ['modes' => $modes, 'max' => $max];
    }

    /**
     * @return non-empty-list<PaginationMode>|null
     */
    private function paginationModes(Expr $expression): ?array
    {
        if (! $expression instanceof Expr\Array_) {
            return null;
        }

        $modes = [];

        foreach ($expression->items as $item) {
            if (! $mode = $this->paginationMode($item->value)) {
                return null;
            }

            if (! in_array($mode, $modes, true)) {
                $modes[] = $mode;
            }
        }

        return $modes ?: null;
    }

    private function paginationMode(Expr $expression): ?PaginationMode
    {
        if (! $expression instanceof Expr\ClassConstFetch
            || ! $this->isClassName($expression->class, PaginationMode::class)) {
            return null;
        }

        return match ($this->methodName($expression->name)) {
            'Default' => PaginationMode::Default,
            'Simple' => PaginationMode::Simple,
            'Cursor' => PaginationMode::Cursor,
            default => null,
        };
    }

    /**
     * @param  non-empty-list<PaginationMode>  $modes
     * @return list<Parameter>
     */
    private function apiPaginationParameters(array $modes, int $max): array
    {
        $parameters = [];

        if (in_array(PaginationMode::Default, $modes, true)
            || in_array(PaginationMode::Simple, $modes, true)) {
            $parameters[] = $this->integerParameter('page', 'The page number to retrieve.');
        }

        if (in_array(PaginationMode::Cursor, $modes, true)) {
            $parameters[] = $this->cursorParameter();
        }

        $parameters[] = $this->integerParameter(
            'per_page',
            'The number of items to retrieve per page.',
            max: $max,
        );

        if (count($modes) > 1) {
            $type = (new StringType)
                ->enum(array_map(fn (PaginationMode $mode): string => $mode->value, $modes))
                ->default($modes[0]->value);

            $parameters[] = Parameter::make('x-pagination', 'header')
                ->setSchema(Schema::fromType($type));
        }

        return $parameters;
    }

    /**
     * @param  non-empty-list<PaginationMode>  $modes
     */
    private function applyApiPaginationResponse(Operation $operation, array $modes): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response || (string) $response->code !== '200') {
                continue;
            }

            $response->description = Str::replaceStart('Array of', 'Paginated set of', $response->description);

            foreach ($response->content as $content) {
                if (! $content instanceof Schema
                    || ! $content->type instanceof ObjectType
                    || ! $content->type->hasProperty('data')
                    || ! $content->type->getProperty('data') instanceof ArrayType) {
                    continue;
                }

                $branches = array_map(
                    fn (PaginationMode $mode): ObjectType => $this->paginationResponse($content->type, $mode),
                    $modes,
                );

                $content->type = count($branches) === 1
                    ? $branches[0]
                    : (new AnyOf)->setItems(array_map(
                        fn (ObjectType $branch, int $index): PaginationResponseType => new PaginationResponseType(
                            $modes[$index],
                            $branch,
                        ),
                        $branches,
                        array_keys($branches),
                    ));
            }
        }
    }

    private function paginationResponse(ObjectType $base, PaginationMode $mode): ObjectType
    {
        $response = $base->clone();

        unset($response->properties['links'], $response->properties['meta']);

        return $response
            ->setRequired(array_values(array_diff($response->required, ['links', 'meta'])))
            ->addProperty('links', $this->paginationLinks())
            ->addProperty('meta', $this->paginationMeta($mode))
            ->addRequired(['data', 'links', 'meta']);
    }

    private function paginationLinks(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('first', (new StringType)->nullable(true))
            ->addProperty('last', (new StringType)->nullable(true))
            ->addProperty('prev', (new StringType)->nullable(true))
            ->addProperty('next', (new StringType)->nullable(true))
            ->setRequired(['first', 'last', 'prev', 'next']);
    }

    private function paginationMeta(PaginationMode $mode): ObjectType
    {
        return match ($mode) {
            PaginationMode::Default => $this->defaultPaginationMeta(),
            PaginationMode::Simple => $this->simplePaginationMeta(),
            PaginationMode::Cursor => $this->cursorPaginationMeta(),
        };
    }

    private function defaultPaginationMeta(): ObjectType
    {
        $link = (new ObjectType)
            ->addProperty('url', (new StringType)->nullable(true))
            ->addProperty('label', new StringType)
            ->addProperty('active', new BooleanType)
            ->setRequired(['url', 'label', 'active']);

        return (new ObjectType)
            ->addProperty('current_page', (new IntegerType)->setMin(1))
            ->addProperty('from', (new IntegerType)->setMin(1)->nullable(true))
            ->addProperty('last_page', (new IntegerType)->setMin(1))
            ->addProperty('links', (new ArrayType)->setItems($link))
            ->addProperty('path', (new StringType)->nullable(true))
            ->addProperty('per_page', (new IntegerType)->setMin(0))
            ->addProperty('to', (new IntegerType)->setMin(1)->nullable(true))
            ->addProperty('total', (new IntegerType)->setMin(0))
            ->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']);
    }

    private function simplePaginationMeta(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('current_page', (new IntegerType)->setMin(1))
            ->addProperty('from', (new IntegerType)->setMin(1)->nullable(true))
            ->addProperty('path', (new StringType)->nullable(true))
            ->addProperty('per_page', (new IntegerType)->setMin(0))
            ->addProperty('to', (new IntegerType)->setMin(1)->nullable(true))
            ->setRequired(['current_page', 'from', 'path', 'per_page', 'to']);
    }

    private function cursorPaginationMeta(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('path', (new StringType)->nullable(true))
            ->addProperty('per_page', (new IntegerType)->setMin(0))
            ->addProperty('next_cursor', (new StringType)->nullable(true))
            ->addProperty('prev_cursor', (new StringType)->nullable(true))
            ->setRequired(['path', 'per_page', 'next_cursor', 'prev_cursor']);
    }

    private function pageParameter(Expr\MethodCall $call): Parameter
    {
        $name = $this->stringArgument($call->args, 2, 'pageName') ?? 'page';

        return $this->integerParameter($name, 'The page number to retrieve.');
    }

    private function cursorParameter(?Expr\MethodCall $call = null): Parameter
    {
        $name = $call === null
            ? 'cursor'
            : $this->stringArgument($call->args, 2, 'cursorName') ?? 'cursor';

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

    private function integerParameter(
        string $name,
        string $description,
        ?int $default = null,
        ?int $max = null,
    ): Parameter {
        $type = (new IntegerType)->setMin(1);

        if ($default !== null) {
            $type->default($default);
        }

        if ($max !== null) {
            $type->setMax($max);
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

        $argument = $arguments[$position] ?? null;

        return $argument !== null && $argument->name === null
            ? $argument->value
            : null;
    }
}
