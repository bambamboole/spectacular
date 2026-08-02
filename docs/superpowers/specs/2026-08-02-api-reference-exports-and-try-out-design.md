# API Reference Exports and Try Out

## Context

PR #11 replaced the API reference viewer's hand-rolled badges, pills, copy affordances, inputs, selects, and icons with components shipped by `@lattice-php/lattice`. The viewer now presents the documented OpenAPI operations consistently with a Lattice application, but it still lacks three core workflows:

1. Copy the current operation as Markdown.
2. Generate and copy request snippets in cURL and JavaScript.
3. Fill request values and execute the operation directly from the browser.

The current `resources/js/api-reference/OperationView.tsx` also owns most operation rendering. Adding all three workflows there would mix documentation rendering, editable request state, serialization, snippet generation, and network execution in one component. This change should introduce focused boundaries while continuing the reuse direction established in PR #11.

## Goals

- Copy the currently selected operation as stable, self-contained Markdown.
- Generate cURL and JavaScript `fetch` snippets from the user's current request values.
- Let users fill path, query, and header parameters plus a JSON request body.
- Execute the filled request directly in the browser against the selected OpenAPI server.
- Accept an optional bearer token from the PHP `ApiReference` component for authenticated live requests.
- Keep the real token out of copied Markdown, copied snippets, errors, and persisted browser state.
- Reuse Lattice primitives and add a generally useful primitive to Lattice instead of hand-rolling one in Spectacular when an actual primitive gap is found.
- Add automated interaction coverage for the new frontend behavior.

## Non-goals

- Copying the entire API reference as Markdown.
- Generating snippets for languages other than cURL and JavaScript in the first release.
- Server-side request proxying.
- A generated nested form for request-body schema properties.
- `multipart/form-data` or `application/x-www-form-urlencoded` request execution.
- Complex OpenAPI parameter serialization styles for array and object parameters; they remain documented but are not executable in the first release.
- Manually setting cookie parameters from the browser.
- Persistent credentials or a general-purpose authorization dialog.
- Redesigning the existing API-reference navigation or documented response presentation.

## Design principles

### Reuse Lattice before adding local primitives

The feature composes Lattice's existing `Button`, `CopyButton`, `Input`, `Textarea`, `Label`, `NativeSelect`, `SegmentedPills`, `Spinner`, `Badge`, and `Card` primitives where they fit. Before adding any reusable UI control locally, check Lattice. If the missing control is useful across Lattice applications, add or extend it in Lattice and consume it from Spectacular. API-reference-specific request, snippet, and OpenAPI behavior remains in Spectacular.

No Lattice change is currently required. The first JSON editor deliberately uses Lattice `Textarea`; a richer code-editor primitive can be considered separately when more than this one feature needs it.

### One normalized request drives every dynamic output

The editable form, cURL output, JavaScript output, and browser executor must not each interpret OpenAPI independently. A single normalized request representation carries the resolved method, URL, headers, and optional JSON body. Snippet templates and the browser executor consume that representation.

The Markdown export is intentionally separate. It serializes the documented operation and never reads the user's form values.

## Architecture

### Parsed operation model

`parseOperation()` remains responsible for turning an OpenAPI operation into viewer-friendly data. Its request metadata expands only as needed to initialize the playground:

- Parameter examples, defaults, enums, primitive type, and required state.
- JSON request-body media type, schema, examples, and required state.
- Existing security metadata for documentation output.

OpenAPI parsing does not perform form-state management, snippet formatting, or request execution.

### Markdown serializer

A pure `operation-markdown.ts` module converts the parsed `Operation` into Markdown containing:

- HTTP method, path, title, and description.
- Documented authorization requirements.
- Path, query, header, and cookie parameter tables.
- JSON request description, schema, and example when present.
- Response statuses and media types.
- Response headers, schemas, and examples.

The serializer receives no live token and no playground state. “Copy Markdown” appears in the operation header beside the existing copyable URL and uses a secondary Lattice `Button` with clipboard feedback.

### Editable request state

A focused `request-state.ts` module initializes and validates the playground state:

- Primitive path, query, and header parameters become editable controls.
- Enum parameters use Lattice `NativeSelect`; other scalar parameters use Lattice `Input` with an appropriate input type.
- Optional boolean parameters use a Lattice `NativeSelect` with blank, `true`, and `false` choices so omission remains distinct from `false`. Required booleans omit the blank choice.
- Required parameters expose accessible required state and field-level validation.
- Cookie parameters remain in the documentation but are not editable because browsers do not allow JavaScript to set the `Cookie` header.
- The JSON body uses a Lattice `Textarea`.

The JSON editor is seeded from the first available source in this order:

1. The selected media type's direct `example`.
2. Its first named example.
3. A deterministic sample derived from the schema by preferring `example`, then `default`, then the first `enum` value, followed by a type-appropriate placeholder.

Only JSON-compatible request bodies (`application/json` and media types ending in `+json`) are executable in the first release. Other media types remain documented without live request controls. If an operation exposes multiple JSON-compatible request media types, the playground uses Lattice `NativeSelect` to choose one and sends that exact content type.

### Request builder

A pure `request-builder.ts` module converts the current state into a normalized request:

- Required path placeholders are URI-component encoded and substituted into the path.
- Non-empty query values are encoded with `URLSearchParams`.
- Non-empty header values are included after rejecting browser-forbidden header names.
- Valid JSON becomes the request body and adds the selected JSON-compatible media type as `Content-Type`.
- The selected server URL and operation path form the final URL.
- An optional configured token adds `Authorization: Bearer <real token>` to executable requests.

The normalized request is the only input to snippet generation and live execution.

### Snippet templates

A small `SnippetTemplate` interface defines a stable extension point for snippet languages. The first implementations are cURL and JavaScript `fetch`.

Both templates consume the current normalized request, so path, query, header, and JSON edits update the displayed code immediately. When authorization is configured, templates replace the real bearer value with `Bearer <YOUR_TOKEN>`. The real token must never enter the snippet string.

The UI uses Lattice `SegmentedPills` to select cURL or JavaScript, a read-only horizontally scrollable code block, and Lattice `CopyButton` to copy the selected snippet.

### Browser executor

An `execute-request.ts` module calls browser `fetch` with the normalized executable request. It returns a normalized result containing:

- HTTP status and status text.
- Duration.
- Response headers.
- A formatted JSON body when the response contains valid JSON.
- Text when the body is not valid JSON or uses another content type.

The browser's normal CORS rules apply. Fetch's default same-origin credential behavior remains intact, allowing existing same-origin login cookies to accompany requests. The optional bearer token is added independently.

A new execution aborts the previous request, and unmounting the playground aborts any in-flight request.

### React composition

`RequestPlayground.tsx` owns editable state and composes smaller views for parameter groups, the JSON body, snippet selection, execution controls, and the live result. It delegates all parsing, serialization, request construction, and network normalization to pure modules.

`OperationView.tsx` remains the operation-level orchestrator. It renders the current documentation, the Markdown action, and the playground without absorbing their internal logic. The current file may be split along existing documentation-section boundaries where that keeps each changed file focused, but unrelated visual or navigation refactoring is out of scope.

## Interaction design

The operation header adds “Copy Markdown” beside the existing operation URL.

The request playground appears after the documented request body and before documented responses. It is presented as a Lattice card with:

1. Path, query, and header parameter groups.
2. A JSON body editor when the operation has a JSON-compatible request body.
3. A cURL/JavaScript snippet switcher and copy action.
4. A primary “Try it out” button.
5. A live-response panel below the action.

The live-response panel remains visually distinct from documented response contracts. It preserves the last result while inputs change and labels the response as live. It shows a status badge, duration, headers, and body.

At narrow widths, controls stack vertically. Generated code scrolls horizontally, while long URLs and response bodies wrap or scroll without widening the page.

All controls have associated labels. Required state is exposed to assistive technology. Copy and execution feedback uses an `aria-live` region. When validation fails, focus moves to the first invalid control. Language selection and every action remain keyboard operable.

## PHP API and token safety

The PHP component gains:

```php
public ?string $token = null;

public function token(string $token): static
{
    $this->token = $token;

    return $this;
}
```

The token is serialized to the browser because browser execution needs it. It is held only in component memory and is never written to local storage, session storage, the URL, Markdown, snippets, live-response errors, or copied output.

When a token is configured:

- The executable request receives `Authorization: Bearer <real token>`.
- cURL and JavaScript snippets receive `Authorization: Bearer <YOUR_TOKEN>`.

When no token is configured, neither executable requests nor snippets receive an authorization header automatically. Existing documented security requirements remain informational.

## Validation and error handling

- Missing required path, query, or header values prevent execution and show field-level errors.
- Invalid JSON prevents execution and preserves the entered text.
- Browser-forbidden header names prevent execution with a specific validation message.
- HTTP 4xx and 5xx responses are valid live results and display their status, headers, and body.
- CORS, DNS, offline, and other transport failures display a clear live-response error without exposing the request token.
- An intentionally aborted superseded request does not replace the current result with an error.
- Empty response bodies render as empty instead of producing a parse failure.
- Valid JSON is pretty-printed; malformed JSON and other content fall back to text.

## Testing

Follow Lattice's own split Vitest setup instead of introducing a separate jsdom/Testing Library convention:

- Keep pure parser, serializer, request-builder, snippet, and executor tests in the normal Vitest project.
- Add a Playwright-backed browser project for `*.browser.test.tsx` files.
- Render React components with `vitest-browser-react` and use Vitest Browser locators and `expect.element()` assertions.
- Run the browser project headlessly in Chromium with Lattice's `data-test` locator convention and browser cleanup setup.

Align the browser test packages with the versions used by the local Lattice project: Vitest 4, `@vitest/browser-playwright`, `playwright`, and `vitest-browser-react`. Spectacular does not need `jsdom`, `@testing-library/react`, or `@testing-library/user-event` for this feature.

Automated coverage includes:

- Markdown serialization for a representative operation, including parameters, authorization requirements, JSON request documentation, responses, and response headers.
- Deterministic schema-derived JSON samples and precedence of direct/named examples.
- Request initialization and validation.
- URL substitution and encoding, query encoding, headers, JSON bodies, and forbidden-header rejection.
- cURL and JavaScript templates, live updates from current values, and bearer-token placeholder redaction.
- Executor normalization for JSON, text, empty bodies, HTTP errors, transport errors, duration, and aborts.
- Playwright-backed React browser interactions for editing values, validation focus, snippet switching and copying, loading state, successful results, HTTP errors, network errors, and abort behavior.
- PHP component serialization and fluent `token()` configuration.

The workbench OpenAPI fixture must exercise path, query, and header parameters plus an `application/json` request body. Manual verification executes a same-origin workbench operation, confirms the request reaches the endpoint, checks the live response and both snippet languages, verifies Markdown output, and confirms the browser console is clean.

Final verification runs:

```bash
npm run typecheck
npm test
npm run build
composer check
```

Any intentional generated OpenAPI fixture change is committed with the corresponding workbench source change.

## Delivery sequence

The implementation should land as independently reviewable vertical slices:

1. Parsed request metadata and deterministic request initialization.
2. Current-operation Markdown export.
3. Normalized request builder and cURL/JavaScript templates.
4. Interactive playground and browser execution.
5. PHP bearer-token configuration, workbench coverage, and full browser verification.

Each slice uses tests first and ends in a meaningful conventional commit. The detailed implementation plan will identify exact files, interfaces, commands, and expected failures for each task.
