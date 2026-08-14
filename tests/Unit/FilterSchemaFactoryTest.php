<?php
declare(strict_types=1);

use Bambamboole\Spectacular\OpenApi\Filters\FilterKind;
use Bambamboole\Spectacular\OpenApi\Filters\FilterSchemaFactory;
use Bambamboole\Spectacular\Tests\Fixtures\Filters\Invoice;
use Bambamboole\Spectacular\Tests\Fixtures\ModelStates\Order;

it('types an exact filter from the column it filters', function (string $name, array $schema): void {
    $type = new FilterSchemaFactory()->make(Invoice::class, $name, FilterKind::Exact);

    expect($type->toArray())->toBe($schema);
})->with([
    'uuid primary key' => ['id', ['type' => 'string', 'format' => 'uuid']],
    'backed enum cast' => ['status', ['type' => 'string', 'enum' => ['open', 'paid']]],
    'boolean cast' => ['is_draft', ['type' => 'boolean']],
    'integer cast' => ['reminders', ['type' => 'integer']],
    'decimal cast' => ['total', ['type' => 'number']],
    'datetime cast' => ['paid_at', ['type' => 'string', 'format' => 'date-time']],
    'belongs to an integer keyed model' => ['customer_id', ['type' => 'integer']],
    'belongs to a uuid keyed model' => ['parent_id', ['type' => 'string', 'format' => 'uuid']],
    'column the model says nothing about' => ['reference', ['type' => 'string']],
    'relation that is not a belongs to' => ['lines_id', ['type' => 'string']],
]);

it('offers the state names of a model state cast', function (): void {
    $type = new FilterSchemaFactory()->make(Order::class, 'status', FilterKind::Exact);

    expect($type->toArray())->toBe(['type' => 'string', 'enum' => ['pending', 'shipped']]);
});

it('types a belongs to filter named after its relation', function (): void {
    $type = new FilterSchemaFactory()->make(Invoice::class, 'customer', FilterKind::BelongsTo);

    expect($type->toArray())->toBe(['type' => 'integer']);
});

it('leaves a text matching filter a string on a typed column', function (FilterKind $kind): void {
    $type = new FilterSchemaFactory()->make(Invoice::class, 'status', $kind);

    expect($type->toArray())->toBe(['type' => 'string']);
})->with([
    'partial' => [FilterKind::Partial],
    'beginsWith' => [FilterKind::BeginsWith],
    'endsWith' => [FilterKind::EndsWith],
    'scope' => [FilterKind::Scope],
    'callback' => [FilterKind::Callback],
]);

it('offers the values the trashed filter accepts', function (): void {
    $type = new FilterSchemaFactory()->make(Invoice::class, 'trashed', FilterKind::Trashed);

    expect($type->toArray())->toBe(['type' => 'string', 'enum' => ['with', 'only', '']]);
});

it('falls back to a string without a model to inspect', function (): void {
    $type = new FilterSchemaFactory()->make(null, 'status', FilterKind::Exact);

    expect($type->toArray())->toBe(['type' => 'string']);
});
