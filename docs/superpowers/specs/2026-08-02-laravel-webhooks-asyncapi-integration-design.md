# Laravel Webhooks AsyncAPI Integration

## Context

Pull request #7 adds two AsyncAPI sources to Spectacular: Laravel broadcast notifications and outbound webhook events. The pull request also implements webhook discovery, payload envelopes, subscriptions, and Spatie-backed delivery inside Spectacular. That runtime functionality has since moved to `bambamboole/laravel-webhooks`, where it now includes database-backed subscriptions, automatic listener registration, delivery history, event caching, and optional envelope links.

Spectacular should retain responsibility for describing application messages as AsyncAPI, while Laravel Webhooks should own webhook definition discovery and runtime delivery.

## Goals

- Update PR #7 onto the latest `main` without losing broadcast-notification support.
- Use Laravel Webhooks as the single source of truth for webhook attributes, discovery, metadata, payload conventions, subscriptions, and delivery.
- Generate AsyncAPI webhook channels, operations, messages, headers, and payload schemas from Laravel Webhooks definitions.
- Remove duplicated webhook runtime code and documentation from Spectacular.
- Keep generated webhook documentation aligned with the payload envelope actually sent by Laravel Webhooks.

## Non-goals

- Reimplement or wrap Laravel Webhooks delivery, persistence, caching, or subscription APIs.
- Preserve Spectacular's provisional webhook PHP API from the unmerged PR.
- Make webhook support optional at runtime through conditional class checks.
- Change existing OpenAPI or broadcast-event behavior.

## Dependency and Ownership Boundary

Spectacular will require `bambamboole/laravel-webhooks:^0.2` as a normal Composer dependency. A hard dependency is intentional: webhook AsyncAPI generation consumes concrete Laravel Webhooks definitions, and an optional integration would create conditional behavior and an undocumented no-op state.

Laravel Webhooks owns:

- `#[WebhookEvent]` and its event metadata;
- configured scan paths, discovery, duplicate-name validation, and cache loading;
- payload envelope construction and optional `webhookLinks()` support;
- subscriptions, dispatch, signing, retries, and delivery history.

Spectacular owns:

- converting discovered webhook definitions into AsyncAPI 3.0 channels, operations, and messages;
- static schema inference for `webhookPayload()` and optional `webhookLinks()` methods;
- AsyncAPI-specific channel address and header schema configuration;
- Spectacular-specific AsyncAPI extensions.

## Architecture and Data Flow

`AsyncApiGenerator` will continue to combine multiple message-definition sources. Broadcast events and broadcast notifications will use Spectacular's existing reflection-based source. Webhooks will come from `Bambamboole\LaravelWebhooks\WebhookEventRegistry::all()`.

For each `WebhookEventDefinition`, `MessageDefinitionFactory` will:

1. Read the event name, class, title, summary, description, and tags from Laravel Webhooks.
2. Infer the `data` schema from the event class's public zero-argument `webhookPayload()` method.
3. Infer an optional `links` schema when the event class exposes `webhookLinks()`.
4. Build the documented Laravel Webhooks envelope with `id`, `event`, `createdAt`, optional `links`, and `data`.
5. Attach the AsyncAPI header schema configured by Spectacular.
6. Place the message on the configured webhook channel and add the existing webhook-specific Spectacular extensions.

Spectacular will not accept a second set of webhook scan paths. The registry will use Laravel Webhooks' `webhooks.scan_paths` setting and its cached definitions, ensuring documentation and runtime listeners observe the same event set.

## Configuration

`spectacular.asyncapi.webhooks` will contain only documentation concerns:

- channel key;
- channel address;
- header schemas representing the outbound HTTP request.

The provisional `scan_paths` and `dispatcher` settings will be removed. Runtime configuration remains under Laravel Webhooks' `webhooks` configuration.

## Code and Documentation Removal

The provisional `Bambamboole\Spectacular\Webhooks` namespace, Spectacular's webhook attribute, its service-container bindings, dispatch tests, registry tests, subscription examples, and optional Spatie dependency guidance will be removed from the PR.

The README will show the Laravel Webhooks attribute and explain that installing/configuring Laravel Webhooks defines the webhook runtime. Spectacular's documentation will cover only how those definitions appear in the generated AsyncAPI document.

## Compatibility and Error Handling

The webhook API introduced only on the unmerged branch is not a supported compatibility surface, so no aliases or deprecation layer will be added.

Duplicate event names, invalid scan paths, and cache behavior remain Laravel Webhooks responsibilities. Spectacular will preserve its current defensive schema inference behavior for methods that cannot be reflected or whose return types cannot be resolved. An empty Laravel Webhooks registry will simply produce no webhook messages.

## Testing

Implementation will follow test-first development. The focused test suite will prove:

- a Laravel Webhooks `#[WebhookEvent]` is discovered and rendered as an AsyncAPI message;
- event metadata and tags flow from `WebhookEventDefinition`;
- the documented envelope includes `id`, `event`, `createdAt`, and `data`;
- `links` is documented only for event classes that expose `webhookLinks()`;
- configured webhook headers and channel settings are rendered;
- broadcast-notification support remains unchanged;
- duplicate runtime webhook registries and delivery classes are absent from Spectacular.

The final verification gate will run the complete Composer checks, JavaScript typecheck/tests/build where present, inspect the branch diff against current `main`, and verify PR #7's GitHub checks after pushing.

## Branch and Pull Request Update

The existing `feat/webhook-events-asyncapi` branch will be rebased onto the latest `origin/main`. The obsolete webhook-runtime commits will be replaced by the narrower integration, while the broadcast-notification work is retained. After local verification, the rewritten branch will be pushed with `--force-with-lease` to update PR #7 safely. The PR title and body will be updated to describe the Laravel Webhooks dependency, retained notification support, removed duplication, and verification evidence.
