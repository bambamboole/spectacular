<?php
declare(strict_types=1);

use Bambamboole\Spectacular\AsyncApi\AsyncApiGenerator;
use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;
use Bambamboole\Spectacular\SpectacularServiceProvider;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\BroadcastStatus;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\ImmediateBroadcast;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\InvoicePaidWebhook;
use Bambamboole\Spectacular\Tests\Fixtures\AsyncApi\UserNotificationBroadcast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use ReflectionAttribute;
use ReflectionClass;
use Workbench\App\Providers\WorkbenchServiceProvider;

it('defaults to scanning the application events path', function (): void {
    $config = require dirname(__DIR__, 2).'/config/spectacular.php';

    expect($config['asyncapi']['scan_paths'])->toBe([app_path('Events')]);
});

it('defaults webhook AsyncAPI settings without enabling runtime delivery', function (): void {
    $config = require dirname(__DIR__, 2).'/config/spectacular.php';

    expect($config['asyncapi']['webhooks'])->toBe([
        'scan_paths' => null,
        'channel' => [
            'key' => 'webhooks',
            'address' => '{webhookUrl}',
        ],
        'headers' => [
            'Content-Type' => ['type' => 'string', 'enum' => ['application/json']],
            'Signature' => ['type' => 'string'],
            'Timestamp' => ['type' => 'integer'],
        ],
        'dispatcher' => [
            'enabled' => false,
            'use_timestamp' => true,
        ],
    ]);
});

it('fills webhook AsyncAPI defaults for older published configs', function (): void {
    $publishedScanPaths = [dirname(__DIR__).'/Fixtures/AsyncApi'];

    config()->set('spectacular.asyncapi', [
        'version' => '3.0.0',
        'default_content_type' => 'application/json',
        'info' => [
            'title' => 'Published AsyncAPI',
            'version' => '9.9.9',
        ],
        'laravel_extensions' => false,
        'scan_paths' => $publishedScanPaths,
    ]);

    (new SpectacularServiceProvider(app()))->register();

    expect(config('spectacular.asyncapi.webhooks.channel.key'))->toBe('webhooks')
        ->and(config('spectacular.asyncapi.webhooks.scan_paths'))->toBeNull()
        ->and(config('spectacular.asyncapi.scan_paths'))->toBe($publishedScanPaths);
});

it('treats specialized async attributes as message metadata', function (): void {
    $notification = new ReflectionClass(BroadcastNotification::class);
    $webhook = new ReflectionClass(WebhookEvent::class);

    expect($notification->isSubclassOf(Message::class))->toBeTrue()
        ->and($webhook->isSubclassOf(Message::class))->toBeTrue();

    $attribute = new BroadcastNotification(
        notifiables: ['App\\Models\\User'],
        title: 'Invoice paid',
        summary: 'Sent after an invoice is paid',
        tags: ['billing'],
    );

    expect($attribute->notifiables)->toBe(['App\\Models\\User'])
        ->and($attribute->title)->toBe('Invoice paid')
        ->and($attribute->summary)->toBe('Sent after an invoice is paid')
        ->and($attribute->tags)->toBe(['billing']);

    $webhookAttribute = new WebhookEvent(
        name: 'invoice.paid',
        payloadMethod: 'webhookPayload',
        title: 'Invoice paid',
        tags: ['billing'],
    );

    expect($webhookAttribute->name)->toBe('invoice.paid')
        ->and($webhookAttribute->payloadMethod)->toBe('webhookPayload')
        ->and($webhookAttribute->title)->toBe('Invoice paid')
        ->and($webhookAttribute->tags)->toBe(['billing']);

    $reflectedAttributes = (new ReflectionClass(InvoicePaidWebhook::class))
        ->getAttributes(Message::class, ReflectionAttribute::IS_INSTANCEOF);

    expect($reflectedAttributes)->toHaveCount(1);
});

it('generates an AsyncAPI document for tagged Laravel broadcast events', function (): void {
    configureFixtureAsyncApi();

    $document = app(AsyncApiGenerator::class)->generate();
    $notificationMessage = $document['components']['messages']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast'];
    $immediateMessage = $document['components']['messages']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.ImmediateBroadcast'];

    expect($document['asyncapi'])->toBe('3.0.0')
        ->and($document['info'])->toBe(['title' => 'Test AsyncAPI', 'version' => '1.2.3'])
        ->and($document['defaultContentType'])->toBe('application/json')
        ->and($document['channels']['private-users.{userId}']['address'])->toBe('private-users.{userId}')
        ->and($document['channels']['private-users.{userId}']['x-laravel-channel-type'])->toBe('private')
        ->and($document['channels']['orders']['x-laravel-channel-type'])->toBe('public')
        ->and($document['operations']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast.send']['action'])->toBe('send')
        ->and($document['operations']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast.send']['messages'][0]['$ref'])->toBe('#/channels/private-users.{userId}/messages/Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast')
        ->and($notificationMessage['name'])->toBe('user.notification.created')
        ->and($notificationMessage['title'])->toBe('User Notification')
        ->and($notificationMessage['summary'])->toBe('User notification was created')
        ->and($notificationMessage['description'])->toBe('Sent when a user receives a notification.')
        ->and($notificationMessage['tags'])->toBe([['name' => 'notifications']])
        ->and($notificationMessage['x-laravel-event'])->toBe(UserNotificationBroadcast::class)
        ->and($notificationMessage['x-laravel-broadcast-now'])->toBeFalse()
        ->and($immediateMessage['name'])->toBe(ImmediateBroadcast::class)
        ->and($immediateMessage['x-laravel-broadcast-now'])->toBeTrue();
});

it('infers literal broadcastOn channels when the Message attribute omits channels', function (): void {
    configureFixtureAsyncApi();

    $document = app(AsyncApiGenerator::class)->generate();

    expect($document['channels']['orders']['address'])->toBe('orders')
        ->and($document['channels']['orders']['messages'])
        ->toHaveKey('Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.ImmediateBroadcast');
});

it('can omit Laravel extension fields', function (): void {
    configureFixtureAsyncApi();

    config()->set('spectacular.asyncapi.laravel_extensions', false);

    $document = app(AsyncApiGenerator::class)->generate();
    $message = $document['components']['messages']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast'];

    expect($document['channels']['private-users.{userId}'])->not->toHaveKey('x-laravel-channel-type')
        ->and($message)->not->toHaveKey('x-laravel-event')
        ->and($message)->not->toHaveKey('x-laravel-broadcast-now');
});

it('uses broadcastWith array shapes as the message payload schema', function (): void {
    configureFixtureAsyncApi();

    $payload = app(AsyncApiGenerator::class)
        ->generate()['components']['messages']['Bambamboole.Spectacular.Tests.Fixtures.AsyncApi.UserNotificationBroadcast']['payload'];

    expect($payload['type'])->toBe('object')
        ->and($payload['required'])->toBe(['notificationId', 'team', 'urgent', 'tags', 'sentAt', 'status'])
        ->and($payload['properties']['notificationId'])->toBe(['type' => 'integer'])
        ->and($payload['properties']['team'])->toBe(['type' => 'string'])
        ->and($payload['properties']['urgent'])->toBe(['type' => 'boolean'])
        ->and($payload['properties']['tags'])->toBe(['type' => 'array', 'items' => ['type' => 'string']])
        ->and($payload['properties']['sentAt'])->toBe([
            'type' => 'string',
            'format' => 'date-time',
            'x-php-type' => CarbonImmutable::class,
        ])
        ->and($payload['properties']['status'])->toBe([
            'type' => 'string',
            'enum' => ['pending', 'sent'],
            'x-php-type' => BroadcastStatus::class,
        ]);
});

it('writes the generated document to stdout or to a file path', function (): void {
    configureFixtureAsyncApi();

    $path = sys_get_temp_dir().'/spectacular-asyncapi-command.json';

    if (file_exists($path)) {
        unlink($path);
    }

    expect(Artisan::call('spectacular:asyncapi'))->toBe(0)
        ->and(Artisan::output())->toContain('"asyncapi": "3.0.0"');

    expect(Artisan::call('spectacular:asyncapi', ['--path' => $path]))->toBe(0);

    expect(json_decode((string) file_get_contents($path), true))
        ->toBe(app(AsyncApiGenerator::class)->generate());

    unlink($path);
});

it('matches the workbench AsyncAPI fixture', function (): void {
    app()->register(WorkbenchServiceProvider::class);

    expect(config('spectacular.asyncapi.scan_paths'))->toBe([dirname(__DIR__, 2).'/workbench/app/Events'])
        ->and(workbenchAsyncApiFixturePath())->toBeFile()
        ->and(generatedWorkbenchAsyncApiJson())->toBe(file_get_contents(workbenchAsyncApiFixturePath()));
});

function configureFixtureAsyncApi(): void
{
    config()->set('spectacular.asyncapi', [
        'version' => '3.0.0',
        'default_content_type' => 'application/json',
        'laravel_extensions' => true,
        'info' => [
            'title' => 'Test AsyncAPI',
            'version' => '1.2.3',
        ],
        'scan_paths' => [
            dirname(__DIR__).'/Fixtures/AsyncApi',
        ],
    ]);
}

function generatedWorkbenchAsyncApiJson(): string
{
    return json_encode(app(AsyncApiGenerator::class)->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
}

function workbenchAsyncApiFixturePath(): string
{
    return dirname(__DIR__, 2).'/workbench/fixtures/asyncapi.json';
}
