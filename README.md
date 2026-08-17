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

Spectacular ships Scramble [operation extensions](https://scramble.dedoc.co/usage/extending) for query builder
parameters, pagination and its own documentation attributes, along with transformers that document validation errors,
rate limits, laravel-data request bodies and the info object. The service provider registers them for you; add your own
through Scramble's native `scramble.extensions` config.

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
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }
}
```

Produces `filter[name]`, `filter[email]`, `sort` and `include` parameters — with enums, descriptions and the correct
array styling — plus `page` and `per_page` from the extension below. Spectacular does not document `allowedFields()`
because it limits selected database columns rather than the fields serialized by a standard Laravel JSON resource.
Laravel `JsonApiResource` sparse fieldsets are handled separately by Scramble.

Filter, sort and include names honour the relevant `config/query-builder.php` settings, so a customised query-builder
config is reflected in the generated document.

#### Filter schemas and matching

The `AllowedFilter` factory a filter was declared with decides both how it is described and whether the model can type
it. `exact`, `belongsTo` and `operator` compare a whole column value, so the schema comes from the model named in
`QueryBuilder::for()`:

| The column | Documented as |
| --- | --- |
| A backed enum cast | `string` or `integer` with the case values as `enum` |
| A `boolean` cast | `boolean` |
| An `integer` cast | `integer` |
| A `float`, `double` or `decimal:n` cast | `number` |
| A `date` or `datetime` cast | `string` with `format: date` / `date-time` |
| The model's own key | Its key type, `format: uuid` with `HasUuids` |
| A `*_id` naming a `BelongsTo` relation | The related model's key |

`AllowedFilter::trashed()` documents the `with`, `only` and empty values it accepts. Text matching (`partial`,
`beginsWith`, `endsWith`) stays a `string` whatever the column holds, because a client sends a fragment rather than a
value. A filter whose semantics Spectacular cannot know (`callback`, `custom`) and a chain opened with something other
than a model class stay untyped.

A `scope` filter points at a method the developer already documented: when the scope method — `#[Scope]`-attributed or
legacy `scope`-prefixed, own, inherited, or from a trait — carries a docblock summary, that summary becomes the
parameter description instead of the generic template.

### API filters, sorts, and includes

Filters, sorts, and includes shared across every API endpoint of a model do not have to be re-declared in every
controller. A model implementing one or more of the API contracts declares them once:

```php
use Bambamboole\Spectacular\Contracts\HasApiFilters;
use Bambamboole\Spectacular\Contracts\HasApiIncludes;
use Bambamboole\Spectacular\Contracts\HasApiSorts;
use Spatie\QueryBuilder\AllowedFilter;

class User extends Model implements HasApiFilters, HasApiIncludes, HasApiSorts
{
    public static function getApiFilters(): array
    {
        return [
            AllowedFilter::scope('created_after'),
            AllowedFilter::scope('created_before'),
        ];
    }

    public static function getApiSorts(): array
    {
        return ['created_at', 'updated_at'];
    }

    public static function getApiIncludes(): array
    {
        return ['roles'];
    }
}
```

Every chain opened with the Spectacular `QueryBuilder` on that model then allows and documents these declarations
automatically: without matching `allowedFilters()`, `allowedSorts()`, or `allowedIncludes()` calls; merged into explicit
calls, where the explicit declaration wins over an API declaration with the same name; and on relation subjects such as
`QueryBuilder::for($tenant->members())`. Scope filters are the typical filter use case, but any `AllowedFilter` works.
A string include such as `roles` also allows and documents `rolesCount` and `rolesExists`, matching Spatie's behavior.
Unknown filters, sorts, and includes are rejected as usual: any call that completes the chain — `apiPaginate()`, `get()`,
`findOrFail()`, or any other query execution — enforces the model API declarations even without explicit declaration
calls. Chains opened with the plain Spatie `QueryBuilder` are not affected: they neither allow nor document model API
declarations.

### Single-result endpoints

A show endpoint returns one record: there is nothing to filter or sort, but includes still make sense. Complete the
chain with `apiFindOrFail()` instead of `findOrFail()`:

```php
return new UserResource(
    QueryBuilder::for(User::class)->apiFindOrFail($id),
);
```

`apiFindOrFail()` allows and validates the model's API includes, rejects any `filter` or `sort` request parameter with
a 400 before the lookup runs, and documents only the `include` parameter on the endpoint — the model's API filters and
sorts stay off single-result operations.

### Pagination parameters

`paginate()`, `simplePaginate()` and `cursorPaginate()` on a query-builder chain are documented automatically:

- `paginate` / `simplePaginate` → a `page` integer parameter (minimum `1`).
- `cursorPaginate` → a `cursor` string parameter.
- A `per_page`-style parameter is derived from a `$request->integer('per_page', 15)` (or `input`/`query`) argument,
  including its default.

Custom page/cursor names (`pageName`, `cursorName`) and the per-page key are read from the call arguments.

To let clients choose between pagination modes, use Spectacular's query builder:

```php
use Bambamboole\Spectacular\PaginationMode;
use Bambamboole\Spectacular\QueryBuilder;

return UserResource::collection(
    QueryBuilder::for(User::class)->apiPaginate(
        modes: [PaginationMode::Default, PaginationMode::Cursor],
        max: 100,
    ),
);
```

Available modes are `Default`, `Simple` and `Cursor`. Modes default to `[PaginationMode::Default]`; when several are
declared, the first is the default and clients select another with the `x-pagination` header. The API reference renders
that header as a select and includes it in generated and live requests. OpenAPI responses use titled `anyOf` branches
for each declared mode.

`per_page` defaults to the model's page size. Supplied integers are clamped between `1` and `max`, which defaults to
`100`. A non-integer `per_page` or an unavailable `x-pagination` mode fails validation, so operations using
`apiPaginate()` document a `422` response even on reading endpoints.

### Authentication modes

Inside your own app the reference can borrow a token from the session. A public reference cannot, so the document has to
state how a reader is meant to authenticate. Declare the modes in config instead of assembling scheme objects:

```php
// config/spectacular.php
'openapi' => [
    'security' => [
        'middleware' => ['auth:api'],
        'schemes' => [
            'bearer' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'description' => 'A personal access token.',
            ],
            'oauth2' => [
                'type' => 'oauth2',
                'flows' => [
                    'authorizationCode' => [
                        'authorization_url' => '/oauth/authorize',
                        'token_url' => '/oauth/token',
                        'scopes' => ApiScopes::class,
                    ],
                    'clientCredentials' => ['token_url' => '/oauth/token'],
                ],
            ],
        ],
    ],
],
```

Each entry becomes an entry in `components.securitySchemes` and a document-level requirement; several entries read as
alternatives, so a client picks one. Supported types are `http`, `apiKey`, `oauth2`, `openIdConnect` and `mutualTLS`.
Relative URLs are resolved against the app URL, absolute ones are kept — handy when authorization lives on a separate
identity host.

Scopes are usually derived from the app itself, which a cached config file cannot hold. Besides a literal
`['scope' => 'description']` map, `scopes` accepts an invokable class-string that is resolved through the container and
returns one:

```php
final class ApiScopes
{
    public function __construct(private PermissionRepository $permissions) {}

    /** @return array<string, string> */
    public function __invoke(): array
    {
        return $this->permissions->apiScopes();
    }
}
```

A route carrying none of the `middleware` patterns is documented as public (`security: []`). Operations that already
declare their own requirement — per-endpoint scopes, for instance — are left untouched. Leave `schemes` empty to keep
documenting security yourself.

### Validation errors

Scramble infers a `422` only where it can see validation happen inside the controller — a `validate()` call or a Form
Request. Spectacular documents one on every `POST`, `PUT` and `PATCH` operation instead, referencing a single
`ValidationException` response component with Laravel's `message` and `errors` body. An operation that already documents
a `422` is left untouched.

### Rate limits

Throttling happens in middleware, which no controller body reveals — without it an endpoint reads as if a client could
call it as often as it likes. A route carrying one of the configured middleware patterns documents its request budget on
every success response, plus a shared `ThrottleRequestsException` response for an exhausted limit:

```php
// config/spectacular.php
'rate_limiting' => [
    'middleware' => ['throttle', 'throttle:*'],
    'headers' => [
        'X-RateLimit-Limit' => 'The maximum number of requests allowed in the current window.',
        'X-RateLimit-Remaining' => 'The number of requests left in the current window.',
    ],
    'exhausted_headers' => [
        'Retry-After' => 'Seconds to wait before sending another request.',
        'X-RateLimit-Reset' => 'Seconds until the current window resets.',
    ],
],
```

The defaults describe what Laravel's own `throttle` middleware returns. An app throttling through a middleware of its
own adds that alias to `middleware`; one that sets a further header while the limit holds moves it from
`exhausted_headers` into `headers`. Header values are documented as integers. An operation that already documents a
`429` is left untouched, and leaving `middleware` or `headers` empty keeps rate limits undocumented.

### The info object

Scramble resolves the document title from `scramble.ui.title` and the version and description from `scramble.info`.
What OpenAPI offers beyond those three is only available here:

```php
// config/spectacular.php
'info' => [
    'description' => 'What this API is for.',
    'terms_of_service' => 'https://acme.test/terms',
    'contact' => ['name' => 'API support', 'email' => 'api@acme.test'],
    'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
],
```

Anything set here wins over what Scramble resolved; anything left out keeps it. A licence needs a `name` to be
documented at all, and OpenAPI allows an SPDX `identifier` or a `url` but not both — given both, the `url` is dropped.

### laravel-data request bodies

When `spatie/laravel-data` is installed, an action that takes a `Data` object gets its request body documented — without
it such endpoints appear to accept nothing at all, because the validation happens while the container resolves the
argument rather than in the controller body.

```php
final class StoreArticleData extends Data
{
    public function __construct(
        /** Headline of the article. */
        public string $title,
        /** Teaser shown in listings. */
        public ?string $summary = null,
        /** Whether the article is publicly visible. Defaults to false. */
        #[MapInputName('is_published')]
        public bool $isPublished = false,
    ) {}
}
```

The docblock above a promoted property becomes that field's `description`, so a payload is described where it is
declared instead of in a per-endpoint attribute.

Which fields are mandatory is taken from the properties themselves, not from the generated rules: a property carrying a
default or a nullable type may be left out, even though its rules say `required` once it is present. An optional object
holding a mandatory field also stays optional — only `title` is required above, and an omitted `summary` is not an
error. A `Data` class that nests itself is expanded once and then documented as an unconstrained array, which keeps a
tree-shaped payload from recursing forever.

A `Data` class declaring its own `rules()` method is left to Scramble, which reads that method directly.

### State transition endpoints

When `spatie/laravel-model-states` is installed, a templated transition route such as
`PATCH /api/orders/{order}/transition-to/{state}` is fanned out into one documented operation per reachable target
state — `…/transition-to/paid`, `…/transition-to/cancelled` — each with a distinct summary, the source states that
allow it, a shared `409 Conflict` response, and exactly the request body its transition expects. The base state class
opts in with a marker attribute; there is nothing to configure:

```php
#[StateEndpoint]
abstract class OrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->allowTransition(Pending::class, Paid::class)
            ->allowTransition(Pending::class, Cancelled::class, MarkAsCancelled::class);
    }
}
```

A documented route qualifies when its URI carries a `{state}` parameter and its action binds a model that casts a field
to the annotated state class — path and HTTP method come from the route itself. The attribute's optional `label` (the
noun used in summaries and descriptions) defaults to the state class basename without its `State` suffix, lowercased.

A transition takes a request body by declaring a `laravel-data` object in its custom transition constructor after the
model; the documented operation then requires exactly that body, described like any other data payload. Transitions
without a data class document without a request body. All source states of one target must agree on the payload, since
a single operation cannot carry a different body per current state.

```php
final class MarkAsCancelled extends Transition
{
    public function __construct(
        private readonly Order $order,
        private readonly CancelOrderData $data,
    ) {}
}
```

The runtime side ships too: `TransitionModelState` takes the payload as a plain array — no HTTP coupling, so jobs and
actions can drive transitions the same way. It resolves the target state from its morph name, validates the payload
against the transition's data class when one exists (rejecting undeclared keys, and any payload on transitions that
take none), executes the transition inside a database transaction, and throws `StateTransitionDenied` — self-rendering
as the documented `409` with `current_state`, `requested_state`, and `allowed_states` — when the current state does not
allow the move. A controller needs one line:

```php
Route::patch('orders/{order}/transition-to/{state}', OrderTransitionsController::class);

final class OrderTransitionsController
{
    public function __invoke(Order $order, string $state, Request $request, TransitionModelState $transition): OrderResource
    {
        return new OrderResource($transition->handle($order, 'status', $state, $request->json()->all()));
    }
}
```

Both messages the runtime produces are translatable under the `spectacular::states` namespace
(`php artisan vendor:publish --tag=spectacular-lang`).

### Documentation attributes

Three attributes document a payload field, a parameter and an endpoint. Each takes a `tooltip`: a short piece of HTML,
links included, emitted as `x-tooltip` next to the `description` it belongs to, for an API reference to render beside
the field.

`#[SpecProperty]` documents a laravel-data payload field:

```php
use Bambamboole\Spectacular\Attributes\SpecProperty;

final class StoreCategoryData extends Data
{
    public function __construct(
        #[SpecProperty(
            description: 'Display name of the category.',
            tooltip: 'Shown in navigation. Read the <a href="/docs/categories">category guide</a>.',
        )]
        public string $name,
        /** Publication state of the category. Drafts stay hidden. */
        #[SpecProperty(tooltip: 'Only <code>published</code> categories appear in the storefront.')]
        public CategoryStatus $status = CategoryStatus::Draft,
    ) {}
}
```

Docblocks stay a valid way to describe a field, and the two mix freely: `status` above keeps its docblock description
and gains a tooltip. When a property carries both a docblock and a `description` in the attribute, the attribute wins.

`#[SpecParameter]` documents a single parameter of an endpoint, selected by name. It is repeatable, and it reaches both
path parameters and the query parameters Spectacular generates itself (`filter[…]`, `sort`, `include`, `page`,
`per_page`, `cursor`) — which is what it takes to describe a filter in your own words:

```php
use Bambamboole\Spectacular\Attributes\SpecParameter;

#[SpecParameter(
    'filter[status]',
    description: 'Filter by publication state.',
    tooltip: 'One of <code>draft</code>, <code>published</code> or <code>archived</code>.',
)]
#[SpecParameter('user', description: 'Identifier of the user to load.')]
public function __invoke(Request $request): AnonymousResourceCollection
```

A `default` can be documented alongside, and it lands on the parameter's schema. A path parameter needs no
`#[PathParameter]` of Scramble's next to it.

`#[SpecEndpoint]` adds a tooltip to the operation. Its title and description stay with Scramble's own `#[Endpoint]`:

```php
use Bambamboole\Spectacular\Attributes\SpecEndpoint;

#[SpecEndpoint(tooltip: 'Creating a category requires the <code>categories:write</code> scope.')]
public function __invoke(StoreCategoryData $data): CategoryResource
```

Response resources are not covered: a `JsonResource::toArray()` describes its fields through docblocks, and a PHP
attribute cannot attach to a key of an array literal.

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

A class named in a payload — a `JsonResource`, a laravel-data object, a plain object — is described rather than
flattened to a bare `object`. It becomes a `$ref` into `components/schemas`, and the referenced schemas (including the
enums and nested resources they pull in) are published in the document alongside the messages:

```php
/** @return array{category: CategoryResource, publishedBy: string} */
public function webhookPayload(): array { /* ... */ }
```

```json
{
  "properties": {
    "category": { "$ref": "#/components/schemas/CategoryResource" },
    "publishedBy": { "type": "string" }
  }
}
```

That is what lets events sharing an abstract base class document their payload: give the concrete event a
`webhookPayload()` override whose only job is to carry the narrower `@return` docblock.

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

Webhook documentation is optional and needs [`bambamboole/laravel-webhooks`](https://github.com/bambamboole/laravel-webhooks)
(install separately); without it, the AsyncAPI document simply contains no webhook channel. Use its `#[WebhookEvent]`
attribute on outbound webhook event classes you want listed in the AsyncAPI document:

```php
use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;

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

Laravel Webhooks owns runtime event discovery, subscriptions, delivery, signing, retries, caching, and delivery
history. Follow the [Laravel Webhooks documentation](https://github.com/bambamboole/laravel-webhooks) to configure
those runtime concerns.

Spectacular limits its webhook role to the generated AsyncAPI channel, message metadata, envelope schema, configured
headers, and the `spectacular:asyncapi` command.

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
        'channel' => [
            'key' => 'webhooks',
            'address' => '{webhookUrl}',
        ],
        'headers' => [
            'Content-Type' => ['type' => 'string', 'enum' => ['application/json']],
            'Signature' => ['type' => 'string'],
            'Timestamp' => ['type' => 'integer'],
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

## Displaying docs with Lattice

![Spectacular API reference](.github/assets/api-reference.png)

The interactive API reference viewer lives in [`lattice-php/api-reference`](https://github.com/lattice-php/api-reference),
a first-party [Lattice](https://latticephp.com) component package. It renders any generated OpenAPI document as a
browsable reference with a request playground — see the
[docs page with a live demo](https://latticephp.com/packages/api-reference/):

```bash
composer require lattice-php/api-reference
```

```php
use Dedoc\Scramble\Generator;
use Illuminate\Support\Facades\Cache;
use Lattice\ApiReference\ApiReference;
use Lattice\Core\Attributes\AsPage;
use Lattice\Http\Page;
use Lattice\Ui\PageSchema;

#[AsPage(route: 'docs', name: 'docs', middleware: ['auth'])]
final class ApiDocsPage extends Page
{
    public function render(PageSchema $schema, Generator $generator): PageSchema
    {
        $document = Cache::rememberForever(
            'spectacular.openapi',
            fn () => $generator(),
        );

        return $schema->schema([ApiReference::make()->spec($document)]);
    }
}
```

Scramble's generator only needs `phpstan/phpdoc-parser` and `nikic/php-parser` at runtime (PHPStan itself is a
`require-dev` package of Scramble), so generating the document on request works in production. It still walks your
whole app via reflection and AST parsing though, so cache the result rather than regenerating it per request —
`Cache::forget('spectacular.openapi')` on deploy, or a shorter TTL, both work. Gate the page behind `middleware` (or
an env check) if the reference shouldn't be public.

## Testing

```bash
composer test      # Pest
composer check     # Pint (test), PHPStan, Pest — mirrors CI
```

The package is developed with [Orchestra Testbench](https://github.com/orchestral/testbench); the `workbench/` app
provides the routes and events exercised by the test suite.

## License

Spectacular is open-sourced software licensed under the [MIT license](LICENSE.md).
