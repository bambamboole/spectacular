<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Restates which body fields are mandatory once the request body schema exists.
 *
 * Two things upstream cannot know: a laravel-data property with a default or a
 * nullable type may be omitted even though its rules say `required` when present,
 * and Scramble promotes a nested object to required as soon as one of its own
 * properties is required — which turns an optional object carrying a mandatory
 * field into a mandatory object.
 */
final readonly class DataRequiredFieldsTransformer implements OperationTransformer
{
    public function __construct(private OpenApiContext $context) {}

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

        $type->setRequired(array_values(array_intersect(
            DataObjects::requiredInputNames($dataClass),
            array_keys($type->properties),
        )));
    }
}
