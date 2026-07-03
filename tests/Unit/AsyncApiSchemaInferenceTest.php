<?php
declare(strict_types=1);

use Bambamboole\Spectacular\AsyncApi\Support\PayloadSchemaFactory;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\BroadcastStatus;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\ExternalPayload;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\PublicPropertiesBroadcast;
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
