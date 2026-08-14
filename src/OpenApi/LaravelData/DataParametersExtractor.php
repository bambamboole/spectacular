<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor\ParameterExtractor;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\ParametersExtractionResult;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Documents the request body of a route action that takes a laravel-data object.
 * Scramble only understands validation it can see in the controller, so without
 * this such endpoints appear to accept nothing at all.
 */
final readonly class DataParametersExtractor implements ParameterExtractor
{
    public function __construct(private TypeTransformer $typeTransformer) {}

    /**
     * @param  ParametersExtractionResult[]  $parameterExtractionResults
     * @return ParametersExtractionResult[]
     */
    public function handle(RouteInfo $routeInfo, array $parameterExtractionResults): array
    {
        $dataClass = DataObjects::forRoute($routeInfo);

        if ($dataClass === null) {
            return $parameterExtractionResults;
        }

        $parameterExtractionResults[] = new ParametersExtractionResult(
            parameters: new DataSchemaFactory($this->typeTransformer)->bodyParameters($dataClass),
            schemaName: class_basename($dataClass),
            sourceClass: $dataClass,
        );

        return $parameterExtractionResults;
    }
}
