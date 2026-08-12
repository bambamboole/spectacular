<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Transformers;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Validation\ValidationException;

/**
 * Documents a validation error on every writing endpoint.
 *
 * Scramble infers a 422 only where it can see validation happen inside the
 * controller — a `validate()` call or a Form Request. An endpoint that validates
 * anywhere else, such as while the container resolves a laravel-data argument,
 * would otherwise claim it cannot fail validation.
 */
final readonly class ValidationErrorResponses implements OperationTransformer
{
    private const array WRITE_METHODS = ['post', 'put', 'patch'];

    public function __construct(private OpenApiContext $context) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! in_array(strtolower($operation->method), self::WRITE_METHODS, true)) {
            return;
        }

        $components = $this->context->openApi->components;
        $reference = new Reference('responses', '\\'.ValidationException::class, $components);

        if (! $components->has($reference)) {
            $components->add($reference, $this->response());
        }

        foreach ($operation->responses ?? [] as $response) {
            $documented = $response instanceof Reference
                ? $response->fullName === $reference->fullName
                : $response->code === 422;

            if ($documented) {
                return;
            }
        }

        $operation->addResponse($reference);
    }

    private function response(): Response
    {
        $body = new ObjectType()
            ->addProperty('message', new StringType()->setDescription('Errors overview.'))
            ->addProperty(
                'errors',
                new ObjectType()
                    ->setDescription('A detailed description of each field that failed validation.')
                    ->additionalProperties(new ArrayType()->setItems(new StringType)),
            )
            ->setRequired(['message', 'errors']);

        return Response::make(422)
            ->setDescription('Validation error')
            ->setContent('application/json', Schema::fromType($body));
    }
}
