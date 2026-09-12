<?php

declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi;

use Bambamboole\Spectacular\PaginationMode;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * The paginated envelopes a Laravel API emits, in one place so the JsonResource
 * and the laravel-data response paths cannot drift apart.
 *
 * They are not the same shape: a JsonResource collection summarises the
 * paginator into `links` (first/last/prev/next) and moves the page links into
 * `meta`, while laravel-data hands the paginator's own array through — there
 * `links` is the page-link list and the page URLs stay in `meta`.
 */
final class PaginationEnvelope
{
    public static function resourceLinks(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('first', self::nullableString())
            ->addProperty('last', self::nullableString())
            ->addProperty('prev', self::nullableString())
            ->addProperty('next', self::nullableString())
            ->setRequired(['first', 'last', 'prev', 'next']);
    }

    public static function resourceMeta(PaginationMode $mode): ObjectType
    {
        return match ($mode) {
            PaginationMode::Default => (new ObjectType)
                ->addProperty('current_page', self::pageNumber())
                ->addProperty('from', self::pageNumber()->nullable(true))
                ->addProperty('last_page', self::pageNumber())
                ->addProperty('links', self::pageLinks())
                ->addProperty('path', self::nullableString())
                ->addProperty('per_page', self::itemCount())
                ->addProperty('to', self::pageNumber()->nullable(true))
                ->addProperty('total', self::itemCount())
                ->setRequired(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total']),
            PaginationMode::Simple => (new ObjectType)
                ->addProperty('current_page', self::pageNumber())
                ->addProperty('from', self::pageNumber()->nullable(true))
                ->addProperty('path', self::nullableString())
                ->addProperty('per_page', self::itemCount())
                ->addProperty('to', self::pageNumber()->nullable(true))
                ->setRequired(['current_page', 'from', 'path', 'per_page', 'to']),
            PaginationMode::Cursor => (new ObjectType)
                ->addProperty('path', self::nullableString())
                ->addProperty('per_page', self::itemCount())
                ->addProperty('next_cursor', self::nullableString())
                ->addProperty('prev_cursor', self::nullableString())
                ->setRequired(['path', 'per_page', 'next_cursor', 'prev_cursor']),
        };
    }

    public static function pageLinks(): ArrayType
    {
        $link = (new ObjectType)
            ->addProperty('url', self::nullableString())
            ->addProperty('label', new StringType)
            ->addProperty('active', new BooleanType)
            ->setRequired(['url', 'label', 'active']);

        return (new ArrayType)->setItems($link);
    }

    public static function paginatorMeta(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('current_page', self::pageNumber())
            ->addProperty('first_page_url', self::nullableString())
            ->addProperty('from', self::pageNumber()->nullable(true))
            ->addProperty('last_page', self::pageNumber())
            ->addProperty('last_page_url', self::nullableString())
            ->addProperty('next_page_url', self::nullableString())
            ->addProperty('path', self::nullableString())
            ->addProperty('per_page', self::itemCount())
            ->addProperty('prev_page_url', self::nullableString())
            ->addProperty('to', self::pageNumber()->nullable(true))
            ->addProperty('total', self::itemCount())
            ->setRequired([
                'current_page', 'first_page_url', 'from', 'last_page', 'last_page_url', 'next_page_url',
                'path', 'per_page', 'prev_page_url', 'to', 'total',
            ]);
    }

    public static function cursorPaginatorMeta(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('path', self::nullableString())
            ->addProperty('per_page', self::itemCount())
            ->addProperty('next_cursor', self::nullableString())
            ->addProperty('next_page_url', self::nullableString())
            ->addProperty('prev_cursor', self::nullableString())
            ->addProperty('prev_page_url', self::nullableString())
            ->setRequired(['path', 'per_page', 'next_cursor', 'next_page_url', 'prev_cursor', 'prev_page_url']);
    }

    private static function nullableString(): StringType
    {
        return (new StringType)->nullable(true);
    }

    private static function pageNumber(): IntegerType
    {
        return (new IntegerType)->setMin(1);
    }

    private static function itemCount(): IntegerType
    {
        return (new IntegerType)->setMin(0);
    }
}
