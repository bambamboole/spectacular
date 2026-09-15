<?php
declare(strict_types=1);

use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\BroadcastStatus;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\CustomBroadcastWithNotification;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidBroadcastNotification;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\PaymentSettledWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\PublicPropertiesBroadcast;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\UserNotificationBroadcast;

it('infers scalar and array-shape payload entries from broadcastWith PHPDoc', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forEvent(UserNotificationBroadcast::class);

    expect($schema['required'])->toBe(['notificationId', 'team', 'urgent', 'tags', 'sentAt', 'status'])
        ->and($schema['properties']['notificationId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['team'])->toBe(['type' => 'string'])
        ->and($schema['properties']['urgent'])->toBe(['type' => 'boolean'])
        ->and($schema['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
});

it('infers webhook payload schemas from configured payload methods', function (): void {
    $factory = app(PayloadSchemaFactory::class);
    $schema = $factory->forMethod(InvoicePaidWebhook::class, 'webhookPayload');

    expect($schema['required'])->toBe(['invoiceId', 'amount', 'paidAt', 'status'])
        ->and($schema['properties']['invoiceId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['amount'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['paidAt'])->toBe([
            'type' => 'string',
            'format' => 'date-time',
        ])
        ->and($schema['properties']['status'])->toBe(['$ref' => '#/components/schemas/BroadcastStatus'])
        ->and($factory->referencedSchemas()['BroadcastStatus']['enum'])->toBe(['pending', 'sent']);
});

it('infers broadcast notification payload schemas from toBroadcast methods', function (): void {
    $schema = app(PayloadSchemaFactory::class)
        ->forNotification(InvoicePaidBroadcastNotification::class);

    expect($schema['required'])->toBe(['invoiceId', 'amount', 'paidAt', 'id', 'type'])
        ->and($schema['properties']['invoiceId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['amount'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['paidAt'])->toBe([
            'type' => 'string',
            'format' => 'date-time',
        ])
        ->and($schema['properties']['id'])->toBe([
            'type' => 'string',
            'format' => 'uuid',
        ])
        ->and($schema['properties']['type'])->toBe([
            'type' => 'string',
            'enum' => ['invoice.paid'],
        ]);
});

it('falls back to object schemas for malformed array-shape payload docs', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forMethod(MalformedArrayShapePayload::class, 'webhookPayload');

    expect($schema)->toBe(['type' => 'object']);
});

it('infers associative generic payload docs', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forMethod(AssociativePayload::class, 'webhookPayload');

    expect($schema)->toBe([
        'type' => 'object',
        'additionalProperties' => ['type' => 'integer'],
    ]);
});

it('does not add default notification fields when broadcastWith defines the payload', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forNotification(CustomBroadcastWithNotification::class);

    expect($schema['required'])->toBe(['invoiceId'])
        ->and($schema['properties'])->toBe([
            'invoiceId' => ['type' => 'integer'],
        ]);
});

it('infers public properties when broadcastWith is absent', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forEvent(PublicPropertiesBroadcast::class);

    expect($schema['required'])->toBe(['teamId', 'labels', 'status', 'createdAt', 'payload'])
        ->and($schema['properties']['teamId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['displayName'])->toBe(['type' => ['string', 'null']])
        ->and($schema['properties']['labels'])->toBe(['type' => 'array'])
        ->and($schema['properties'])->not->toHaveKey('broadcastQueue');
});

it('maps dates, enums, nullable types, and objects', function (): void {
    $factory = app(PayloadSchemaFactory::class);
    $schema = $factory->forEvent(PublicPropertiesBroadcast::class);

    expect($schema['properties']['status'])->toBe([
        '$ref' => '#/components/schemas/BroadcastStatus',
    ])->and($schema['properties']['createdAt'])->toBe([
        'type' => 'string',
        'format' => 'date-time',
    ])->and($schema['properties']['payload'])->toBe([
        '$ref' => '#/components/schemas/ExternalPayload',
    ])->and($factory->referencedSchemas()['ExternalPayload']['properties']['value'] ?? null)
        ->toBe(['type' => 'string']);
});

it('keeps untyped public property schemas unconstrained', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forEvent(MixedPublicPropertiesBroadcast::class);

    expect($schema['properties']['payload'])->toBe([])
        ->and($schema['required'] ?? null)->toBeNull();
});

it('documents a BigDecimal payload entry as number or decimal string', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forMethod(PaymentSettledWebhook::class, 'webhookPayload');

    expect($schema['properties']['amount'])->toBe([
        'anyOf' => [
            ['type' => 'number'],
            ['type' => 'string', 'pattern' => '^-?\\d+(\\.\\d+)?$'],
        ],
    ]);
});

it('publishes enum payload entries as reusable component schemas', function (): void {
    $factory = app(PayloadSchemaFactory::class);
    $schema = $factory->forMethod(PureEnumPayload::class, 'webhookPayload');
    $schemas = $factory->referencedSchemas();

    expect($schema['properties']['status'])->toBe(['$ref' => '#/components/schemas/BroadcastStatus'])
        ->and($schema['properties']['stage'])->toBe(['$ref' => '#/components/schemas/PureStage'])
        ->and($schemas['BroadcastStatus']['enum'])->toBe(['pending', 'sent'])
        ->and($schemas['PureStage']['enum'])->toBe(['Draft', 'Live']);
});

it('hoists a named payload schema and answers with a reference to it', function (): void {
    $factory = app(PayloadSchemaFactory::class);
    $schema = ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]];

    expect($factory->named('InvoicePaidPayload', $schema))
        ->toBe(['$ref' => '#/components/schemas/InvoicePaidPayload'])
        ->and($factory->referencedSchemas()['InvoicePaidPayload'])
        ->toBe($schema + ['title' => 'InvoicePaidPayload'])
        ->and($factory->named('EmptyPayload', ['type' => 'object']))
        ->toBe(['type' => 'object']);
});

enum PureStage
{
    case Draft;
    case Live;
}

final class PureEnumPayload
{
    /**
     * @return array{status: BroadcastStatus, stage: PureStage}
     */
    public function webhookPayload(): array
    {
        return ['status' => BroadcastStatus::Pending, 'stage' => PureStage::Draft];
    }
}

final class MalformedArrayShapePayload
{
    /**
     * @return array{int}
     */
    public function webhookPayload(): array
    {
        return [123];
    }
}

final class AssociativePayload
{
    /**
     * @return array<string, int>
     */
    public function webhookPayload(): array
    {
        return [];
    }
}

final class MixedPublicPropertiesBroadcast
{
    public mixed $payload;
}
