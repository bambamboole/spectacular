# Spectacular

OpenAPI and AsyncAPI tooling for Laravel applications.

Spectacular gives you two things from the code you already write:

- **OpenAPI** — [Scramble](https://scramble.dedoc.co) extensions that document
  [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) filters, sorts, includes and sparse
  fieldsets, plus pagination parameters, directly from your controller actions — no annotations required.
- **AsyncAPI** — a generator that turns your Laravel broadcast events into an [AsyncAPI 3.0](https://www.asyncapi.com)
  document, inferring channels and message payloads from the event class itself.

## Requirements

- PHP 8.4+
- Laravel 13+
- [`dedoc/scramble`](https://github.com/dedoc/scramble) `^0.13.30` (for the OpenAPI extensions)
- [`spatie/laravel-query-builder`](https://github.com/spatie/laravel-query-builder) `^7.0` (for the query-builder extension)

## Installation

```bash
composer require bambamboole/spectacular
```

The service provider is auto-discovered. Publish the config file if you want to customise the defaults:

```bash
php artisan vendor:publish --tag=spectacular-config
```

This writes `config/spectacular.php`.

## OpenAPI

Spectacular ships two Scramble [operation extensions](https://scramble.dedoc.co/usage/extending). They are registered
for you through `config/spectacular.php`:

```php
'scramble' => [
    'extensions' => [
        Bambamboole\Spectacular\OpenApi\Extensions\QueryBuilderExtension::class,
        Bambamboole\Spectacular\OpenApi\Extensions\PaginationExtension::class,
    ],
],
```

### Query builder parameters

Any action that builds a `Spatie\QueryBuilder\QueryBuilder` chain is inspected statically, and the allowed operations
become documented query parameters:

```php
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UsersController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters('name', AllowedFilter::exact('email'))
            ->allowedSorts('name', 'created_at')
            ->allowedIncludes('roles')
            ->allowedFields('id', 'name', 'email', 'roles.id', 'roles.name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }
}
```

Produces `filter[name]`, `filter[email]`, `sort`, `include`, `fields[users]` and `fields[roles]` parameters — with
enums, descriptions and the correct array styling — plus `page` and `per_page` from the extension below.

Parameter names honour your `config/query-builder.php` settings (`parameters.*`, `suffixes.*`), so a customised
query-builder config is reflected in the generated document.

### Pagination parameters

`paginate()`, `simplePaginate()` and `cursorPaginate()` on a query-builder chain are documented automatically:

- `paginate` / `simplePaginate` → a `page` integer parameter (minimum `1`).
- `cursorPaginate` → a `cursor` string parameter.
- A `per_page`-style parameter is derived from a `$request->integer('per_page', 15)` (or `input`/`query`) argument,
  including its default.

Custom page/cursor names (`pageName`, `cursorName`) and the per-page key are read from the call arguments.

### Generating the document

```bash
php artisan spectacular:openapi                 # print to stdout
php artisan spectacular:openapi --path=openapi.json
php artisan spectacular:openapi --pretty=false  # compact JSON
```

The command renders the same document Scramble produces, so all of Scramble's own configuration applies.

## AsyncAPI

Annotate the broadcast events you want documented with the `#[Message]` attribute. Spectacular scans the configured
paths for events implementing `ShouldBroadcast` / `ShouldBroadcastNow` that carry the attribute:

```php
use Bambamboole\Spectacular\AsyncApi\Attributes\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

#[Message(
    summary: 'User notification was created',
    description: 'Sent when a user receives a notification.',
    tags: ['notifications'],
)]
final class UserNotificationBroadcast implements ShouldBroadcast
{
    public function __construct(public int $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notification.created';
    }

    /**
     * @return array{notificationId: int, team: string, sentAt: \Carbon\CarbonImmutable, status: BroadcastStatus}
     */
    public function broadcastWith(): array
    {
        return [/* ... */];
    }
}
```

From an event, Spectacular derives:

- **Channels** — from the `#[Message(channels: [...])]` argument, or inferred by invoking `broadcastOn()` when the
  attribute omits them. Channel type (`public`, `private`, `presence`, `private-encrypted`) is detected from the name.
- **Message name** — from `broadcastAs()` when present, otherwise the fully-qualified class name.
- **Payload schema** — from the `broadcastWith()` `@return` PHPDoc (array shapes, `list<>`, `array<string, T>`,
  nullable and union types are all understood). When `broadcastWith()` is absent, the event's public properties are
  used, mapping scalars, enums, `DateTimeInterface` and nested objects to JSON Schema.

### The `#[Message]` attribute

```php
#[Message(
    channels: [],          // explicit channel names; inferred from broadcastOn() when empty
    title: null,           // human-friendly message title
    summary: null,         // short message summary
    description: null,     // longer description
    tags: [],              // AsyncAPI message tags
    payload: null,         // reference an external payload schema ($ref) instead of inferring
)]
```

### Broadcast notifications

Use `#[BroadcastNotification]` on Laravel notification classes that are delivered through the `broadcast` channel:

```php
use Bambamboole\Spectacular\AsyncApi\Attributes\BroadcastNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

#[BroadcastNotification(
    notifiables: [User::class],
    title: 'Invoice paid',
    summary: 'Sent to users when an invoice is paid.',
    tags: ['billing'],
)]
final class InvoicePaidNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    /**
     * @return BroadcastMessage&object{data: array{invoiceId:int, amount:int}}
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'invoiceId' => 123,
            'amount' => 4999,
        ]);
    }
}
```

Spectacular infers notification channels from the `notifiables` classes. If a notifiable exposes
`receivesBroadcastNotificationsOn()`, that value is used; otherwise the channel defaults to a private placeholder such
as `private-App.Models.User.{userId}`. Pass explicit `channels` when notifications use custom or dynamic broadcast
channels that cannot be inferred.

### Webhook events

Use `#[WebhookEvent]` on outbound webhook event classes you want listed in the AsyncAPI document and runtime webhook
registry:

```php
use Bambamboole\Spectacular\AsyncApi\Attributes\WebhookEvent;

#[WebhookEvent(
    name: 'invoice.paid',
    title: 'Invoice paid',
    summary: 'Sent when an invoice is paid.',
    tags: ['billing'],
)]
final class InvoicePaidWebhook
{
    public function __construct(public int $invoiceId, public int $amount) {}

    /**
     * @return array{invoiceId:int, amount:int}
     */
    public function webhookPayload(): array
    {
        return [
            'invoiceId' => $this->invoiceId,
            'amount' => $this->amount,
        ];
    }
}
```

`WebhookEventRegistry::all()` returns the discovered definitions, which an app can use to power its own webhook setup
UI:

```php
use Bambamboole\Spectacular\Webhooks\WebhookEventRegistry;

$events = collect(app(WebhookEventRegistry::class)->all())
    ->map(fn ($event) => [
        'name' => $event->name,
        'title' => $event->title,
        'summary' => $event->summary,
    ]);
```

Spectacular documents and discovers webhook events, but your application owns webhook endpoint persistence and any
subscription UI.

### Optional webhook delivery

Runtime delivery is optional and uses `spatie/laravel-webhook-server`:

```bash
composer require spatie/laravel-webhook-server
```

Bind `Bambamboole\Spectacular\Webhooks\WebhookSubscriptionRepository` to an application repository that returns active
`Bambamboole\Spectacular\Webhooks\WebhookSubscription` objects for an event:

```php
use Bambamboole\Spectacular\Webhooks\WebhookSubscription;
use Bambamboole\Spectacular\Webhooks\WebhookSubscriptionRepository;

final class DatabaseWebhookSubscriptionRepository implements WebhookSubscriptionRepository
{
    public function forEvent(string $eventName, object $event): iterable
    {
        return [
            new WebhookSubscription(
                url: 'https://example.com/webhooks',
                secret: 'signing-secret',
                headers: ['X-Webhook-Source' => 'app'],
                id: 'subscription-123',
            ),
        ];
    }
}
```

Then register `Bambamboole\Spectacular\Webhooks\DispatchWebhookEvent` as a listener for the webhook event classes your
app wants delivered. If `spectacular.asyncapi.webhooks.scan_paths` is `null`, webhook discovery uses the base AsyncAPI
`scan_paths`; set it to an explicit empty array to disable webhook event discovery.

### Laravel extensions

By default the document includes `x-laravel-*` extension fields (channel type, source event class, whether it
broadcasts now). Disable them with `laravel_extensions => false` in the config.

### Configuration

```php
// config/spectacular.php
'asyncapi' => [
    'version' => '3.0.0',
    'default_content_type' => 'application/json',
    'info' => [
        'title' => env('APP_NAME', 'Laravel').' AsyncAPI',
        'version' => env('APP_VERSION', '0.0.1'),
    ],
    'laravel_extensions' => true,
    'scan_paths' => [
        app_path('Events'),
    ],
    'webhooks' => [
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
    ],
],
```

### Generating the document

```bash
php artisan spectacular:asyncapi                  # print to stdout
php artisan spectacular:asyncapi --path=asyncapi.json
php artisan spectacular:asyncapi --pretty=false   # compact JSON
```

## Testing

```bash
composer test      # Pest
composer check     # Pint (test), PHPStan, Pest — mirrors CI
```

The package is developed with [Orchestra Testbench](https://github.com/orchestral/testbench); the `workbench/` app
provides the routes and events exercised by the test suite.

## License

Spectacular is open-sourced software licensed under the [MIT license](LICENSE.md).
