<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Refines the request body schema once Scramble has registered it: restates
 * which body fields are mandatory (a laravel-data property with a default or a
 * nullable type may be omitted even though its rules say `required` when
 * present) and promotes nested data classes to referenced component schemas.
 */
final readonly class DataRequiredFieldsTransformer implements OperationTransformer
{
    public function __construct(
        private OpenApiContext $context,
        private TypeTransformer $typeTransformer,
    ) {}

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $dataClass = DataObjects::forRoute($routeInfo);

        if ($dataClass === null) {
            return;
        }

        $components = $this->context->openApi->components;
        $schemaName = class_basename($dataClass);

        if (! $components->hasSchema($schemaName)) {
            return;
        }

        $type = $components->getSchema($schemaName)->type;

        if (! $type instanceof ObjectType) {
            return;
        }

        new DataSchemaFactory($this->typeTransformer)->refineSchema($type, $dataClass, $components);
    }
}
