# Laravel Webhooks AsyncAPI Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update Spectacular PR #7 onto current `main`, preserve broadcast-notification AsyncAPI support, and replace Spectacular's duplicated webhook runtime with `bambamboole/laravel-webhooks`.

**Architecture:** `AsyncApiGenerator` continues to merge broadcast-event, broadcast-notification, and webhook message definitions. Webhooks are sourced exclusively from `Bambamboole\LaravelWebhooks\WebhookEventRegistry`; Spectacular converts those definitions into AsyncAPI channels and messages while Laravel Webhooks owns attributes, discovery, caching, subscriptions, payload envelopes, and delivery.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 5, Composer, AsyncAPI 3.0, `bambamboole/laravel-webhooks:^0.2`, GitHub CLI.

## Global Constraints

- Preserve the existing broadcast-notification support from PR #7.
- Require `bambamboole/laravel-webhooks:^0.2`; do not add conditional class checks or an optional integration layer.
- Keep only AsyncAPI channel and header configuration under `spectacular.asyncapi.webhooks`.
- Use Laravel Webhooks' `webhooks.scan_paths`, registry cache, attribute metadata, and envelope conventions.
- Remove the provisional `Bambamboole\Spectacular\Webhooks` API without aliases because it has never been merged or released.
- Implement behavior changes test-first and observe every focused test fail for the expected reason before production edits.
- Rebase the existing PR branch onto current `origin/main` and update it with `--force-with-lease`, never an unconditional force push.

---

### Task 1: Align the PR branch and install the canonical webhook package

**Files:**
- Modify: `composer.json`
- Retain: `docs/superpowers/specs/2026-08-02-laravel-webhooks-asyncapi-integration-design.md`
- Retain: `docs/superpowers/plans/2026-08-02-laravel-webhooks-asyncapi-integration.md`

**Interfaces:**
- Consumes: remote branch `origin/feat/webhook-events-asyncapi` at the SHA recorded immediately before rewriting.
- Produces: a branch based on current `origin/main` with Laravel Webhooks classes available through Composer autoloading.

- [ ] **Step 1: Record the remote safety point and refresh refs**

```bash
git fetch origin main feat/webhook-events-asyncapi
git rev-parse origin/feat/webhook-events-asyncapi
git status --short --branch
```

Expected: the recorded PR head is `43097548885a7585b70257f1d53049aece079ad1` unless another actor updated it; the worktree contains only the committed design and plan work.

- [ ] **Step 2: Rebase the existing PR work onto current main**

```bash
git rebase origin/main
git status --short --branch
```

Expected: `feat/webhook-events-asyncapi` is based on current `origin/main`. For conflicts, retain current-main versions of unrelated OpenAPI/viewer/dependency changes and retain PR #7's AsyncAPI message-definition and broadcast-notification changes. Continue with `git rebase --continue` after each resolved commit; abort with `git rebase --abort` if the resolution would discard either retained feature.

- [ ] **Step 3: Replace the provisional optional Spatie declaration with Laravel Webhooks**

Edit `composer.json` so the relevant sections are exactly:

```json
"require": {
    "php": "^8.4",
    "bambamboole/laravel-webhooks": "^0.2",
    "dedoc/scramble": "^0.13.30",
    "illuminate/support": "^13.0",
    "spatie/laravel-package-tools": "^1.16 || ^2.0",
    "spatie/laravel-query-builder": "^7.0"
}
```

Remove `spatie/laravel-webhook-server` from `require-dev` and remove the entire provisional `suggest` entry. Laravel Webhooks owns and installs its Spatie delivery dependency.

- [ ] **Step 4: Install dependencies and verify Composer can resolve the integration**

```bash
composer update bambamboole/laravel-webhooks --with-all-dependencies
composer validate --strict
```

Expected: Composer installs a `0.2.x` Laravel Webhooks release and validation exits 0.

- [ ] **Step 5: Commit the dependency boundary**

```bash
git add composer.json docs/superpowers/specs/2026-08-02-laravel-webhooks-asyncapi-integration-design.md docs/superpowers/plans/2026-08-02-laravel-webhooks-asyncapi-integration.md
git commit -m "chore: use Laravel Webhooks for webhook runtime"
```

---

### Task 2: Drive AsyncAPI generation from Laravel Webhooks definitions

**Files:**
- Modify: `tests/TestCase.php`
- Modify: `tests/Feature/AsyncApiGenerationTest.php`
- Modify: `tests/Fixtures/AsyncApi/InvoicePaidWebhook.php`
- Modify: `tests/Fixtures/AsyncApi/InvoiceRefundedWebhook.php`
- Modify: `src/AsyncApi/AsyncApiGenerator.php`
- Modify: `src/AsyncApi/Messages/MessageDefinitionFactory.php`

**Interfaces:**
- Consumes: `Bambamboole\LaravelWebhooks\WebhookEventRegistry::all(): list<WebhookEventDefinition>`.
- Produces: `MessageDefinitionFactory::fromWebhook(WebhookEventDefinition $definition, array $webhooks = []): AsyncMessageDefinition`.
- Produces: webhook AsyncAPI payloads with required `id`, `event`, `createdAt`, and `data`, plus optional `links` when `webhookLinks()` is publicly callable without required arguments.

- [ ] **Step 1: Register Laravel Webhooks in the package test harness**

Add the provider import and provider entry in `tests/TestCase.php`:

```php
use Bambamboole\LaravelWebhooks\WebhooksServiceProvider;

protected function getPackageProviders($app): array
{
    return [
        WebhooksServiceProvider::class,
        ScrambleServiceProvider::class,
        QueryBuilderServiceProvider::class,
        SpectacularServiceProvider::class,
    ];
}
```

- [ ] **Step 2: Replace fixture attributes and add an optional links contract**

In both webhook fixtures, replace the Spectacular attribute import with:

```php
use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;
```

Add this method only to `InvoicePaidWebhook`:

```php
/**
 * @return array{self:string}
 */
public function webhookLinks(): array
{
    return ['self' => 'https://example.test/invoices/'.$this->invoiceId];
}
```

- [ ] **Step 3: Write the failing Laravel Webhooks integration test**

Update `configureFixtureAsyncApi()` to configure the canonical registry and clear its singleton:

```php
config()->set('webhooks.scan_paths', [asyncApiFixturePath()]);
app()->forgetInstance(\Bambamboole\LaravelWebhooks\WebhookEventRegistry::class);
```

Replace the provisional webhook-attribute assertions with a focused integration test:

```php
it('documents webhook definitions discovered by Laravel Webhooks', function (): void {
    configureFixtureAsyncApi();

    $document = app(AsyncApiGenerator::class)->generate();
    $paid = $document['components']['messages']['invoice.paid'];
    $refunded = $document['components']['messages']['invoice.refunded'];

    expect($paid['title'])->toBe('Invoice Paid')
        ->and($paid['tags'])->toBe([['name' => 'billing']])
        ->and($paid['payload']['properties']['data']['properties']['invoiceId'])->toBe(['type' => 'integer'])
        ->and($paid['payload']['properties']['links']['properties']['self'])->toBe(['type' => 'string'])
        ->and($paid['payload']['required'])->toBe(['id', 'event', 'createdAt', 'data'])
        ->and($refunded['payload']['properties'])->not->toHaveKey('links');
});
```

- [ ] **Step 4: Run the test and verify RED**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="documents webhook definitions discovered by Laravel Webhooks"
```

Expected: FAIL because `AsyncApiGenerator` still consumes Spectacular's internal registry and does not see the Laravel Webhooks attributes.

- [ ] **Step 5: Switch the generator to the canonical registry**

In `src/AsyncApi/AsyncApiGenerator.php`, import the package registry:

```php
use Bambamboole\LaravelWebhooks\WebhookEventRegistry;
```

Build webhook definitions directly from the registry:

```php
foreach ($this->webhooks->all() as $webhook) {
    $messageDefinitions[] = $this->messages->fromWebhook($webhook, $this->webhookSettings($settings));
}
```

Delete `webhookScanPaths()`. Simplify `resolveSettings()` to avoid special treatment for the removed webhook scan-path override:

```php
private function resolveSettings(array $overrides): array
{
    return array_replace_recursive(config('spectacular.asyncapi', []), $overrides);
}
```

- [ ] **Step 6: Adapt message construction to Laravel Webhooks metadata and envelope semantics**

In `src/AsyncApi/Messages/MessageDefinitionFactory.php`, import:

```php
use Bambamboole\LaravelWebhooks\WebhookEventDefinition;
```

Replace the data and payload-property setup in `fromWebhook()` with:

```php
$data = $this->payloads->forMethod($definition->class, 'webhookPayload');
$properties = [
    'id' => ['type' => 'string', 'format' => 'uuid'],
    'event' => ['type' => 'string', 'enum' => [$definition->name]],
    'createdAt' => ['type' => 'string', 'format' => 'date-time'],
    'data' => $data,
];

$event = new ReflectionClass($definition->class);

if ($event->hasMethod('webhookLinks')) {
    $method = $event->getMethod('webhookLinks');

    if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
        $properties['links'] = $this->payloads->forMethod($definition->class, 'webhookLinks');
    }
}
```

Use those properties in the message payload:

```php
'payload' => [
    'type' => 'object',
    'properties' => $properties,
    'required' => ['id', 'event', 'createdAt', 'data'],
],
```

The package definition has no nested attribute. Read `name`, `class`, `title`, `summary`, `description`, and `tags` directly from it. Change header construction to accept only Spectacular's AsyncAPI settings:

```php
private function webhookHeaders(array $webhooks): array
{
    $headers = $this->normalizeHeaders(
        is_array($webhooks['headers'] ?? null) ? $webhooks['headers'] : [],
    );

    return array_filter([
        'type' => 'object',
        'properties' => $headers,
    ], fn (mixed $value): bool => $value !== []);
}
```

- [ ] **Step 7: Run focused and full AsyncAPI tests and verify GREEN**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="documents webhook definitions discovered by Laravel Webhooks"
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php tests/Unit/AsyncApiSchemaInferenceTest.php
```

Expected: both commands pass; the existing broadcast-notification assertions remain green.

- [ ] **Step 8: Commit the canonical registry integration**

```bash
git add tests/TestCase.php tests/Feature/AsyncApiGenerationTest.php tests/Fixtures/AsyncApi/InvoicePaidWebhook.php tests/Fixtures/AsyncApi/InvoiceRefundedWebhook.php src/AsyncApi/AsyncApiGenerator.php src/AsyncApi/Messages/MessageDefinitionFactory.php
git commit -m "feat: document Laravel webhook events in AsyncAPI"
```

---

### Task 3: Remove Spectacular's duplicated webhook runtime

**Files:**
- Modify: `tests/Feature/AsyncApiGenerationTest.php`
- Modify: `src/SpectacularServiceProvider.php`
- Delete: `src/AsyncApi/Attributes/WebhookEvent.php`
- Delete: `src/Webhooks/DispatchWebhookEvent.php`
- Delete: `src/Webhooks/NullWebhookSubscriptionRepository.php`
- Delete: `src/Webhooks/WebhookEventDefinition.php`
- Delete: `src/Webhooks/WebhookEventRegistry.php`
- Delete: `src/Webhooks/WebhookPayload.php`
- Delete: `src/Webhooks/WebhookPayloadFactory.php`
- Delete: `src/Webhooks/WebhookSubscription.php`
- Delete: `src/Webhooks/WebhookSubscriptionRepository.php`
- Delete: `tests/Feature/WebhookDispatchTest.php`
- Delete: `tests/Feature/WebhookEventRegistryTest.php`
- Delete: `tests/Fixtures/AsyncApi/MalformedPayloadWebhook.php`
- Delete: `tests/Fixtures/WebhookRegistry/DuplicateWebhooks/FirstInvoicePaidWebhook.php`
- Delete: `tests/Fixtures/WebhookRegistry/DuplicateWebhooks/SecondInvoicePaidWebhook.php`
- Delete: `tests/Fixtures/WebhookRegistry/NestedRoot/Nested/InvoiceVoidedWebhook.php`

**Interfaces:**
- Consumes: working Laravel Webhooks integration from Task 2.
- Produces: no loadable class under `Bambamboole\Spectacular\Webhooks` and no Spectacular `WebhookEvent` attribute.

- [ ] **Step 1: Write a failing architecture assertion**

Add to `tests/Feature/AsyncApiGenerationTest.php`:

```php
it('does not expose a duplicate webhook runtime', function (): void {
    expect(class_exists('Bambamboole\\Spectacular\\AsyncApi\\Attributes\\WebhookEvent'))->toBeFalse()
        ->and(class_exists('Bambamboole\\Spectacular\\Webhooks\\WebhookEventRegistry'))->toBeFalse()
        ->and(class_exists('Bambamboole\\Spectacular\\Webhooks\\DispatchWebhookEvent'))->toBeFalse()
        ->and(interface_exists('Bambamboole\\Spectacular\\Webhooks\\WebhookSubscriptionRepository'))->toBeFalse();
});
```

- [ ] **Step 2: Run the assertion and verify RED**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="does not expose a duplicate webhook runtime"
```

Expected: FAIL because all provisional Spectacular webhook classes still exist.

- [ ] **Step 3: Remove internal service bindings**

Keep only Spectacular's AsyncAPI services in `SpectacularServiceProvider::packageRegistered()`:

```php
$this->app->singleton(ClassDiscoverer::class);
$this->app->singleton(PayloadSchemaFactory::class);
$this->app->singleton(MessageDefinitionFactory::class);
$this->app->singleton(AsyncApiGenerator::class);
```

Remove all imports from `Bambamboole\Spectacular\Webhooks`. Retain `mergeAsyncApiWebhookConfigDefaults()` because published older Spectacular configs still need the documentation-only webhook defaults.

- [ ] **Step 4: Delete the duplicate source, tests, and fixtures listed above**

Use patch deletions for every listed file. Do not delete the Laravel-Webhooks-backed `InvoicePaidWebhook` or `InvoiceRefundedWebhook` AsyncAPI fixtures.

- [ ] **Step 5: Run the assertion and related suite and verify GREEN**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="does not expose a duplicate webhook runtime"
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php tests/Unit/AsyncApiSchemaInferenceTest.php
```

Expected: PASS with no autoload errors and broadcast-notification tests still green.

- [ ] **Step 6: Commit the ownership cleanup**

```bash
git add composer.json src tests
git commit -m "refactor: remove duplicated webhook runtime"
```

---

### Task 4: Move configuration, workbench, and documentation to Laravel Webhooks

**Files:**
- Modify: `config/spectacular.php`
- Modify: `README.md`
- Modify: `workbench/app/Events/InvoicePaid.php`
- Modify: `workbench/app/Providers/WorkbenchServiceProvider.php`
- Modify: `workbench/fixtures/asyncapi.json`
- Modify: `tests/Feature/AsyncApiGenerationTest.php`

**Interfaces:**
- Consumes: `webhooks.scan_paths` from Laravel Webhooks.
- Produces: Spectacular config containing only `channel` and `headers` under `asyncapi.webhooks`.
- Produces: a workbench AsyncAPI fixture generated from a Laravel Webhooks attribute.

- [ ] **Step 1: Write the failing configuration-ownership test**

Add to `tests/Feature/AsyncApiGenerationTest.php`:

```php
it('keeps runtime webhook settings in Laravel Webhooks', function (): void {
    $config = require dirname(__DIR__, 2).'/config/spectacular.php';

    expect($config['asyncapi']['webhooks'])->toHaveKeys(['channel', 'headers'])
        ->and($config['asyncapi']['webhooks'])->not->toHaveKeys(['scan_paths', 'dispatcher']);
});
```

- [ ] **Step 2: Run the test and verify RED**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="keeps runtime webhook settings in Laravel Webhooks"
```

Expected: FAIL because the provisional Spectacular config still contains `scan_paths` and `dispatcher`.

- [ ] **Step 3: Reduce Spectacular's webhook config to documentation concerns**

Set `config/spectacular.php` to:

```php
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
```

- [ ] **Step 4: Point the workbench at Laravel Webhooks**

In `workbench/app/Events/InvoicePaid.php`, import:

```php
use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;
```

In `WorkbenchServiceProvider::register()`, replace the Spectacular webhook scan-path setting with:

```php
config()->set('webhooks.scan_paths', [
    dirname(__DIR__, 2).'/app/Events',
]);
```

- [ ] **Step 5: Run the workbench fixture test and verify RED**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="matches the workbench AsyncAPI fixture"
```

Expected: FAIL because the committed JSON fixture still reflects the previous implementation.

- [ ] **Step 6: Regenerate and verify the workbench fixture**

```bash
vendor/bin/testbench spectacular:asyncapi --path=workbench/fixtures/asyncapi.json
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php --filter="matches the workbench AsyncAPI fixture"
```

Expected: the command rewrites the fixture and the focused test passes.

- [ ] **Step 7: Replace runtime instructions in the README**

The webhook section must use:

```php
use Bambamboole\LaravelWebhooks\Attributes\WebhookEvent;
```

State that Laravel Webhooks owns event discovery, subscriptions, delivery, signing, retries, caching, and delivery history. Keep Spectacular documentation limited to the generated AsyncAPI channel, message metadata, envelope schema, configured headers, and the `spectacular:asyncapi` command. Link readers to `https://github.com/bambamboole/laravel-webhooks` for runtime setup. Remove Spectacular-owned repository bindings, listener registration, optional Spatie installation, and dispatcher configuration.

- [ ] **Step 8: Run focused tests and commit**

```bash
vendor/bin/pest tests/Feature/AsyncApiGenerationTest.php
git add config/spectacular.php README.md workbench/app/Events/InvoicePaid.php workbench/app/Providers/WorkbenchServiceProvider.php workbench/fixtures/asyncapi.json tests/Feature/AsyncApiGenerationTest.php
git commit -m "docs: point webhook users to Laravel Webhooks"
```

Expected: the AsyncAPI feature suite passes before the commit.

---

### Task 5: Verify the full branch and update PR #7

**Files:**
- Inspect: all changes from `origin/main...HEAD`
- Update remotely: PR #7 title and body

**Interfaces:**
- Consumes: all prior task commits and the recorded remote safety SHA from Task 1.
- Produces: updated remote branch `feat/webhook-events-asyncapi` and review-ready PR #7.

- [ ] **Step 1: Run all repository verification gates from a clean dependency state**

```bash
composer check
npm run typecheck
npm test
npm run build
```

Expected: all commands exit 0 with no test failures, type errors, lint errors, static-analysis errors, or build errors.

- [ ] **Step 2: Inspect scope and whitespace before publishing**

```bash
git status --short --branch
git diff --check origin/main...HEAD
git diff --stat origin/main...HEAD
git log --oneline origin/main..HEAD
```

Expected: a clean worktree; the diff contains broadcast-notification support, Laravel-Webhooks-backed AsyncAPI integration, tests, fixture, README, spec, and plan—no duplicate runtime implementation or unrelated changes.

- [ ] **Step 3: Push the rewritten PR branch safely**

Use the verified pre-rewrite remote SHA:

```bash
git push --force-with-lease=refs/heads/feat/webhook-events-asyncapi:43097548885a7585b70257f1d53049aece079ad1 origin HEAD:refs/heads/feat/webhook-events-asyncapi
```

Expected: GitHub accepts the lease and updates PR #7. If the lease fails, fetch and inspect the new remote commits; do not overwrite them.

- [ ] **Step 4: Update PR metadata through the GitHub connector**

Set the title to:

```text
feat: document webhooks and broadcast notifications in AsyncAPI
```

The body must summarize:

```markdown
## Summary
- preserve AsyncAPI support for Laravel broadcast notifications
- document events discovered by `bambamboole/laravel-webhooks`
- infer webhook data and optional links schemas from the runtime event classes
- remove Spectacular's superseded webhook registry, subscriptions, and dispatcher

## Ownership boundary
Laravel Webhooks owns attributes, discovery, caching, subscriptions, delivery, signing, retries, and delivery history. Spectacular only maps those definitions into AsyncAPI channels, operations, headers, and payload schemas.

## Verification
- `composer check`
- `npm run typecheck`
- `npm test`
- `npm run build`
```

- [ ] **Step 5: Verify GitHub checks**

```bash
gh pr checks 7 --repo bambamboole/spectacular
gh pr view 7 --repo bambamboole/spectacular --json number,title,headRefName,baseRefName,mergeable,statusCheckRollup,url
```

Expected: PR #7 targets `main`, uses `feat/webhook-events-asyncapi`, reports mergeable after GitHub recomputes it, and all completed checks pass. If a GitHub Actions check fails, invoke the `github:gh-fix-ci` workflow, inspect its job log, reproduce locally, and add a test-first corrective commit before repeating the lease-protected push and checks.
