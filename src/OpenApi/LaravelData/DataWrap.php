<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\LaravelData;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * `data.wrap` decides whether laravel-data nests a response under a key, so it
 * decides the top level of every documented data response.
 */
final class DataWrap
{
    public static function key(): ?string
    {
        $key = config('data.wrap');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public static function wrap(Type $type, string $key): ObjectType
    {
        return (new ObjectType)
            ->addProperty($key, $type)
            ->setRequired([$key]);
    }
}
