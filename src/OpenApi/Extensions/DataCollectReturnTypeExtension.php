<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Extensions;

use Bambamboole\Spectacular\OpenApi\LaravelData\DataCollectable;
use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Spatie\LaravelData\Data;

/**
 * `RealmData::collect($items, PaginatedDataCollection::class)` declares its item
 * type in the call, not in the return type — without this an action documenting
 * itself that way loses the collected class unless it repeats it in a docblock.
 */
final class DataCollectReturnTypeExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(ObjectType|string $type): bool
    {
        return is_a(is_string($type) ? $type : $type->name, Data::class, true);
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if ($event->getName() !== 'collect') {
            return null;
        }

        $into = $event->getArg('into', 1);

        if (! $into instanceof LiteralString || DataCollectable::forClass($into->getValue()) === null) {
            return null;
        }

        return new Generic($into->getValue(), [
            new Union([new IntegerType, new StringType]),
            new ObjectType($event->getCallee()),
        ]);
    }
}
