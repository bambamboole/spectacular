<?php
declare(strict_types=1);

use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\BroadcastStatus;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\CustomBroadcastWithNotification;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\ExternalPayload;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidBroadcastNotification;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\MalformedPayloadWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\PublicPropertiesBroadcast;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\UserNotifiable;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\UserNotificationBroadcast;
use Carbon\CarbonImmutable;

it('infers scalar and array-shape payload entries from broadcastWith PHPDoc', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forEvent(UserNotificationBroadcast::class);

    expect($schema['required'])->toBe(['notificationId', 'team', 'urgent', 'tags', 'sentAt', 'status'])
        ->and($schema['properties']['notificationId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['team'])->toBe(['type' => 'string'])
        ->and($schema['properties']['urgent'])->toBe(['type' => 'boolean'])
        ->and($schema['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
});

it('infers webhook payload schemas from configured payload methods', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forMethod(InvoicePaidWebhook::class, 'webhookPayload');

    expect($schema['required'])->toBe(['invoiceId', 'amount', 'paidAt', 'status'])
        ->and($schema['properties']['invoiceId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['amount'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['paidAt'])->toBe([
            'type' => 'string',
            'format' => 'date-time',
            'x-php-type' => CarbonImmutable::class,
        ])
        ->and($schema['properties']['status'])->toBe([
            'type' => 'string',
            'enum' => ['pending', 'sent'],
            'x-php-type' => BroadcastStatus::class,
        ]);
});

it('infers broadcast notification payload schemas from toBroadcast methods', function (): void {
    $schema = app(PayloadSchemaFactory::class)
        ->forNotification(InvoicePaidBroadcastNotification::class, UserNotifiable::class);

    expect($schema['required'])->toBe(['invoiceId', 'amount', 'paidAt', 'id', 'type'])
        ->and($schema['properties']['invoiceId'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['amount'])->toBe(['type' => 'integer'])
        ->and($schema['properties']['paidAt'])->toBe([
            'type' => 'string',
            'format' => 'date-time',
            'x-php-type' => CarbonImmutable::class,
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
    $schema = app(PayloadSchemaFactory::class)->forMethod(MalformedPayloadWebhook::class, 'webhookPayload');

    expect($schema)->toBe(['type' => 'object']);
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

it('maps dates, enums, nullable types, and unknown objects', function (): void {
    $schema = app(PayloadSchemaFactory::class)->forEvent(PublicPropertiesBroadcast::class);

    expect($schema['properties']['status'])->toBe([
        'type' => 'string',
        'enum' => ['pending', 'sent'],
        'x-php-type' => BroadcastStatus::class,
    ])->and($schema['properties']['createdAt'])->toBe([
        'type' => 'string',
        'format' => 'date-time',
        'x-php-type' => CarbonImmutable::class,
    ])->and($schema['properties']['payload'])->toBe([
        'type' => 'object',
        'x-php-type' => ExternalPayload::class,
    ]);
});
