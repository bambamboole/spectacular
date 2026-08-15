<?php
declare(strict_types=1);

namespace Bambamboole\Spectacular\OpenApi\Filters;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\FiltersBeginsWith;
use Spatie\QueryBuilder\Filters\FiltersBelongsTo;
use Spatie\QueryBuilder\Filters\FiltersCallback;
use Spatie\QueryBuilder\Filters\FiltersEndsWith;
use Spatie\QueryBuilder\Filters\FiltersExact;
use Spatie\QueryBuilder\Filters\FiltersOperator;
use Spatie\QueryBuilder\Filters\FiltersPartial;
use Spatie\QueryBuilder\Filters\FiltersScope;
use Spatie\QueryBuilder\Filters\FiltersTrashed;

/**
 * The `AllowedFilter` factory a filter was declared with. It carries the matching
 * semantics a client needs and decides whether the filter compares against a typed
 * column value or against text.
 */
enum FilterKind: string
{
    case Exact = 'exact';
    case Partial = 'partial';
    case BeginsWith = 'beginsWith';
    case EndsWith = 'endsWith';
    case BelongsTo = 'belongsTo';
    case Scope = 'scope';
    case Callback = 'callback';
    case Custom = 'custom';
    case Operator = 'operator';
    case Trashed = 'trashed';
    case GroupOr = 'groupOr';
    case GroupAnd = 'groupAnd';

    public static function tryFromFactory(?string $factory): self
    {
        return $factory === null ? self::Partial : (self::tryFrom($factory) ?? self::Partial);
    }

    /**
     * A filter only known as a runtime instance reveals its factory through the
     * internal filter class it was constructed with. Begins/ends-with extend the
     * partial filter, so the subclasses have to match first.
     */
    public static function fromAllowedFilter(AllowedFilter $filter): self
    {
        return match (true) {
            $filter->getFilterClass() instanceof FiltersBeginsWith => self::BeginsWith,
            $filter->getFilterClass() instanceof FiltersEndsWith => self::EndsWith,
            $filter->getFilterClass() instanceof FiltersPartial => self::Partial,
            $filter->getFilterClass() instanceof FiltersOperator => self::Operator,
            $filter->getFilterClass() instanceof FiltersExact => self::Exact,
            $filter->getFilterClass() instanceof FiltersScope => self::Scope,
            $filter->getFilterClass() instanceof FiltersTrashed => self::Trashed,
            $filter->getFilterClass() instanceof FiltersBelongsTo => self::BelongsTo,
            $filter->getFilterClass() instanceof FiltersCallback => self::Callback,
            default => self::Custom,
        };
    }

    /**
     * Text matching sends a fragment of the value, so the column type says nothing
     * about what a client may pass.
     */
    public function comparesTypedValues(): bool
    {
        return match ($this) {
            self::Exact, self::BelongsTo, self::Operator => true,
            default => false,
        };
    }

    public function matching(): ?string
    {
        return match ($this) {
            self::Exact => 'Matches the exact value.',
            self::Partial => 'Matches values containing the given text, case-insensitively.',
            self::BeginsWith => 'Matches values starting with the given text, case-insensitively.',
            self::EndsWith => 'Matches values ending with the given text, case-insensitively.',
            self::BelongsTo => 'Matches the key of the related record.',
            self::Scope => 'Applies the query scope of the same name.',
            self::Trashed => 'Includes soft deleted records with `with`, returns only them with `only`.',
            self::GroupOr => 'Matches when any of the grouped filters matches.',
            self::GroupAnd => 'Matches when all of the grouped filters match.',
            default => null,
        };
    }
}
