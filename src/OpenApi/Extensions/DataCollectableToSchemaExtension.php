<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\OpenApi\LaravelData\DataCollectable;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataSchemaFactory;
use Bambamboole\Spectacular\OpenApi\LaravelData\DataWrap;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * A data collectable is Arrayable, so Scramble documents it as an untyped array
 * and loses both the item type and the envelope laravel-data puts around it.
 */
final class DataCollectableToSchemaExtension extends TypeToSchemaExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && DataCollectable::forClass($type->name) !== null;
    }

    /**
     * @param  ObjectType  $type
     */
    #[\Override]
    public function toSchema(Type $type): OpenApiType
    {
        return new DataSchemaFactory($this->openApiTransformer)
            ->collectableType($this->collectable($type), $this->dataClass($type), $this->components);
    }

    /**
     * Only a response wraps a plain collection: `data.wrap` applies to what an
     * action returns, not to a collection nested in another schema. A paginated
     * collectable carries its wrap key everywhere and is wrapped already.
     *
     * @param  ObjectType  $type
     */
    #[\Override]
    public function toResponse(Type $type): Response
    {
        $schema = $this->openApiTransformer->transform($type);
        $key = DataWrap::key();

        if ($key !== null && ! $this->collectable($type)->isAlwaysWrapped()) {
            $schema = DataWrap::wrap($schema, $key);
        }

        return Response::make(200)
            ->description($this->description($type))
            ->setContent('application/json', Schema::fromType($schema));
    }

    private function description(ObjectType $type): string
    {
        $item = ($dataClass = $this->dataClass($type)) === null ? null : class_basename($dataClass);

        return match ($this->collectable($type)) {
            DataCollectable::Collection => $item === null ? 'Array of items' : "Array of `{$item}`",
            default => $item === null ? 'Paginated set' : "Paginated set of `{$item}`",
        };
    }

    private function collectable(ObjectType $type): DataCollectable
    {
        return DataCollectable::forClass($type->name)
            ?? throw new LogicException($type->name.' is not a laravel-data collectable.');
    }

    /**
     * The collected class is only ever known through the generic — declared by
     * the action the way a `JsonResource` collection declares its item type, or
     * read off the `collect()` call by DataCollectReturnTypeExtension.
     *
     * @return class-string<Data>|null
     */
    private function dataClass(ObjectType $type): ?string
    {
        if (! $type instanceof Generic) {
            return null;
        }

        foreach ($type->templateTypes as $templateType) {
            if ($templateType instanceof ObjectType && is_a($templateType->name, Data::class, true)) {
                return $templateType->name;
            }
        }

        return null;
    }
}
