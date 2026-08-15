<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\OpenApi\LaravelData\DataSchemaFactory;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Spatie\LaravelData\Data;

/**
 * A controller returning a data object would document as an empty object —
 * Scramble cannot infer the transformed shape. The response reuses the same
 * component schema the request side derives, so both directions of the wire
 * share one definition (including the oneOf of property-morphable classes).
 */
final class DataToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && is_a($type->name, Data::class, true);
    }

    /**
     * @param  ObjectType  $type
     */
    #[\Override]
    public function toSchema(Type $type): OpenApiType
    {
        /** @var class-string<Data> $dataClass */
        $dataClass = $type->name;

        return new DataSchemaFactory($this->openApiTransformer)->schemaType($dataClass, $this->components);
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('schemas', class_basename($type->name), $this->components);
    }

    /**
     * Scramble's Arrayable fallback would title the response with the class
     * name — noise next to a schema reference that already names the shape.
     *
     * @param  ObjectType  $type
     */
    #[\Override]
    public function toResponse(Type $type): Response
    {
        return Response::make(200)
            ->setContent('application/json', Schema::fromType($this->openApiTransformer->transform($type)));
    }
}
