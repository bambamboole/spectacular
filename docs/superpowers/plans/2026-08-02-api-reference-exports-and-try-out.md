# API Reference Exports and Try Out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add current-operation Markdown export, live cURL and JavaScript snippets, and a browser-only JSON request playground with optional bearer-token injection.

**Architecture:** Keep OpenAPI parsing, documentation serialization, editable request state, normalized request construction, snippet formatting, and browser execution in focused modules. `OperationView` composes a Lattice-based `RequestPlayground`; one normalized request drives both snippets and execution, while Markdown is generated independently from the documented operation.

**Tech Stack:** TypeScript, React 19, `@lattice-php/lattice` 0.36, Vitest 4, Vitest Browser, Playwright/Chromium, PHP 8.4, Laravel 13, Pest 5, Orchestra Testbench.

## Global Constraints

- “Copy as Markdown” exports only the currently selected operation and ignores live form values.
- Snippet languages are cURL and JavaScript `fetch`; both update from current form values.
- Live requests run directly in the browser and remain subject to normal CORS rules.
- Request bodies are JSON-compatible only: `application/json` or media types ending in `+json`.
- The PHP component may supply a bearer token for execution; snippets use `<YOUR_TOKEN>` and Markdown never receives the token.
- Never persist the token or include it in snippets, Markdown, errors, URLs, copied responses, or snapshots.
- Complex array/object parameter serialization, cookie editing, multipart, form-urlencoded bodies, an auth modal, and a server proxy are out of scope.
- Reuse Lattice primitives first. If a genuinely general primitive is missing, change Lattice rather than hand-rolling it in Spectacular.
- Do not change generated OpenAPI output without regenerating and reviewing `workbench/fixtures/openapi.json`.
- Use tests first. Run `vendor/bin/pint --dirty --format agent` after modifying PHP.

---

### Task 1: Add Lattice-style Vitest Browser infrastructure

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `vite.config.ts`
- Create: `resources/js/test/browser-setup.ts`
- Create: `resources/js/test/browser-smoke.browser.test.tsx`

**Interfaces:**
- Produces: a default Node project for `resources/js/**/*.test.{ts,tsx}`
- Produces: a Playwright-backed `browser` project for `resources/js/**/*.browser.test.tsx`
- Produces: `npm run test:browser`

- [ ] **Step 1: Add a failing browser smoke test**

```tsx
import { render } from "vitest-browser-react";
import { describe, expect, it } from "vitest";

describe("browser test setup", () => {
    it("renders React in Chromium", async () => {
        const screen = await render(<button type="button">Ready</button>);

        await expect.element(screen.getByRole("button", { name: "Ready" })).toBeVisible();
    });
});
```

- [ ] **Step 2: Run the browser test and verify the project is unavailable**

Run: `npm run test:browser`

Expected: FAIL because the script/browser provider does not exist.

- [ ] **Step 3: Install the browser-test packages used by Lattice**

Run:

```bash
npm install --save-dev vitest@^4.1.8 @vitest/browser-playwright@^4.1.8 playwright@^1.60.0 vitest-browser-react@^2.2.0
```

Update `package.json` scripts:

```json
{
    "test": "vitest run",
    "test:browser": "vitest run --project browser"
}
```

- [ ] **Step 4: Add the browser cleanup setup**

```ts
import "../../../workbench/resources/css/app.css";
import { afterEach } from "vitest";

(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

afterEach(async () => {
    const { cleanup } = await import("vitest-browser-react");

    await cleanup();
});
```

- [ ] **Step 5: Configure the Node and browser projects**

Add `playwright` import and `test.projects` to `vite.config.ts`:

```ts
import { playwright } from "@vitest/browser-playwright";

test: {
    projects: [
        {
            extends: true,
            test: {
                name: "node",
                environment: "node",
                include: ["resources/js/**/*.test.{ts,tsx}"],
                exclude: ["resources/js/**/*.browser.test.{ts,tsx}"],
            },
        },
        {
            extends: true,
            test: {
                name: "browser",
                include: ["resources/js/**/*.browser.test.tsx"],
                setupFiles: ["resources/js/test/browser-setup.ts"],
                browser: {
                    enabled: true,
                    provider: playwright(),
                    headless: true,
                    locators: {
                        testIdAttribute: "data-test",
                    },
                    viewport: {
                        width: 1280,
                        height: 800,
                    },
                    instances: [{ browser: "chromium" }],
                },
            },
        },
    ],
},
```

- [ ] **Step 6: Run both projects**

Run: `npm run test:browser && npm test`

Expected: browser smoke test PASS; existing 40 Node tests PASS.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json vite.config.ts resources/js/test
git commit -m "test: add Playwright-backed component tests"
```

### Task 2: Parse executable request metadata and derive JSON samples

**Files:**
- Modify: `resources/js/api-reference/types.ts`
- Modify: `resources/js/api-reference/parse.ts`
- Modify: `resources/js/api-reference/parse.test.ts`
- Create: `resources/js/api-reference/schema-example.ts`
- Create: `resources/js/api-reference/schema-example.test.ts`

**Interfaces:**
- Produces: `Param.example: unknown`
- Produces: `Contract.required: boolean`
- Produces: `exampleFromSchema(schema: unknown, components?: unknown): unknown`
- Produces: `initialContractExample(contract: Contract, components?: unknown): unknown`
- Consumes: existing `Contract.examples`, `Contract.schema`, and OpenAPI `components.schemas`

- [ ] **Step 1: Add failing parser tests for parameter examples and request-body required state**

Add a representative parameter:

```ts
parameters: [
    {
        name: "status",
        in: "query",
        required: true,
        example: "active",
        schema: { type: "string", enum: ["active", "disabled"] },
    },
],
requestBody: {
    required: true,
    content: {
        "application/json": {
            schema: { type: "object", properties: { name: { type: "string" } } },
        },
    },
},
```

Assert:

```ts
expect(operation.paramGroups[0].params[0].example).toBe("active");
expect(operation.requests[0].required).toBe(true);
```

Update existing complete `Param` and `Contract` expectations with `example: null` and `required: false`.

- [ ] **Step 2: Run parser tests and verify the new fields are missing**

Run: `npm test -- resources/js/api-reference/parse.test.ts`

Expected: FAIL on missing `example` and `required`.

- [ ] **Step 3: Extend the parsed types and raw OpenAPI shapes**

```ts
export type Param = {
    name: string;
    location: string;
    required: boolean;
    deprecated: boolean;
    description: string | null;
    schema: unknown;
    example: unknown;
};

export type Contract = {
    role: "request" | "response";
    status: string | null;
    mediaType: string | null;
    schema: unknown;
    title: string | null;
    examples: ContractExample[];
    headers: Param[];
    required: boolean;
};
```

Extend `RawParameter` with `example?: unknown`, and request bodies with `required?: boolean`.

Set parameter example precedence to direct parameter `example`, then schema `example`, then schema `default`, otherwise `null`. Set request contracts from `Boolean(resolved.required)`; response contracts always use `required: false`.

- [ ] **Step 4: Run parser tests**

Run: `npm test -- resources/js/api-reference/parse.test.ts`

Expected: PASS.

- [ ] **Step 5: Add failing schema-example tests**

```ts
import { describe, expect, it } from "vitest";
import { exampleFromSchema, initialContractExample } from "./schema-example";

describe("exampleFromSchema", () => {
    it("prefers examples, defaults, and enums before type placeholders", () => {
        expect(exampleFromSchema({ type: "string", example: "shown" })).toBe("shown");
        expect(exampleFromSchema({ type: "integer", default: 10 })).toBe(10);
        expect(exampleFromSchema({ type: "string", enum: ["first", "second"] })).toBe("first");
    });

    it("builds nested object and array samples", () => {
        expect(
            exampleFromSchema({
                type: "object",
                properties: {
                    name: { type: "string" },
                    enabled: { type: "boolean" },
                    tags: { type: "array", items: { type: "string" } },
                },
            }),
        ).toEqual({ name: "string", enabled: false, tags: ["string"] });
    });

    it("resolves local component schema references without recursing forever", () => {
        const components = {
            schemas: {
                User: {
                    type: "object",
                    properties: {
                        name: { type: "string" },
                        manager: { $ref: "#/components/schemas/User" },
                    },
                },
            },
        };

        expect(exampleFromSchema({ $ref: "#/components/schemas/User" }, components)).toEqual({
            name: "string",
            manager: null,
        });
    });
});

describe("initialContractExample", () => {
    it("prefers the first explicit contract example", () => {
        const contract = {
            role: "request" as const,
            status: null,
            mediaType: "application/json",
            schema: { type: "string", example: "schema" },
            title: null,
            examples: [{ name: "named", summary: null, value: { explicit: true } }],
            headers: [],
            required: false,
        };

        expect(initialContractExample(contract)).toEqual({ explicit: true });
    });
});
```

- [ ] **Step 6: Run the sample tests and verify failure**

Run: `npm test -- resources/js/api-reference/schema-example.test.ts`

Expected: FAIL because the module does not exist.

- [ ] **Step 7: Implement deterministic schema examples**

Implement the exact exported functions with:

- `example` → `default` → first `enum` precedence.
- Local `#/components/schemas/*` resolution.
- A visited-reference set returning `null` for cycles.
- Objects containing every declared property in document order.
- Arrays containing one item sample.
- `"string"`, `0`, and `false` scalar placeholders.
- `null` for unsupported/unknown schemas.
- The first `Contract.examples` value before schema derivation.

- [ ] **Step 8: Run focused tests and typecheck**

Run: `npm test -- resources/js/api-reference/parse.test.ts resources/js/api-reference/schema-example.test.ts && npm run typecheck`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/js/api-reference/types.ts resources/js/api-reference/parse.ts resources/js/api-reference/parse.test.ts resources/js/api-reference/schema-example.ts resources/js/api-reference/schema-example.test.ts
git commit -m "feat: expose executable request metadata"
```

### Task 3: Serialize and copy the current operation as Markdown

**Files:**
- Create: `resources/js/api-reference/operation-markdown.ts`
- Create: `resources/js/api-reference/operation-markdown.test.ts`
- Create: `resources/js/api-reference/OperationHeader.tsx`
- Modify: `resources/js/api-reference/OperationView.tsx`

**Interfaces:**
- Produces: `operationToMarkdown(operation: Operation): string`
- Produces: `OperationHeader({ operation, baseUrl }: { operation: Operation; baseUrl?: string | null }): ReactNode`
- Consumes: `Operation`, existing `Badge`, `CopyableText`, and Lattice `CopyButton`

- [ ] **Step 1: Add a failing Markdown serializer test**

Build one operation with security, path/query parameters, a JSON request, response headers, and two responses. Assert one exact Markdown string containing:

````md
# Create widget

`POST /widgets/{widget}`

Creates a widget.

## Authorization

- bearer

## Parameters

| Name | In | Type | Required | Description |
| --- | --- | --- | --- | --- |
| widget | path | string | yes | Widget identifier |

## Request body

**Content-Type:** `application/json`

```json
{
  "name": "Widget"
}
```

## Responses

### 201 application/json
````

Also assert table cells escape `|` and newlines and that absent sections are omitted.

- [ ] **Step 2: Run the serializer test and verify failure**

Run: `npm test -- resources/js/api-reference/operation-markdown.test.ts`

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement pure Markdown serialization**

Use small private helpers for:

- Type labels from schemas.
- Table-cell escaping.
- JSON fences using `JSON.stringify(value, null, 2)`.
- Request/response contract sections.
- Security requirement `OR` groups and scheme scopes.

Do not accept `token`, base URL, or request state as arguments.

- [ ] **Step 4: Run serializer tests**

Run: `npm test -- resources/js/api-reference/operation-markdown.test.ts`

Expected: PASS.

- [ ] **Step 5: Extract the operation header and add Copy Markdown**

`OperationHeader` computes Markdown with `useMemo` and renders:

```tsx
<div className="flex flex-wrap items-center gap-2">
    <Badge color={httpMethodColor(operation.summary.method)} className="text-xs font-semibold uppercase">
        {operation.summary.method}
    </Badge>
    <CopyableText value={operationUrl} label="operation URL">
        <span className="font-mono text-sm text-lt-muted-fg">{operationUrl}</span>
    </CopyableText>
    <CopyButton value={markdown} label="operation as Markdown" testId="copy-operation-markdown" />
    {operation.summary.deprecated ? <Badge color="danger">deprecated</Badge> : null}
</div>
```

Move the title and description into `OperationHeader`, and replace the inline header in `OperationView`.

- [ ] **Step 6: Run typecheck and existing tests**

Run: `npm run typecheck && npm test`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/api-reference/operation-markdown.ts resources/js/api-reference/operation-markdown.test.ts resources/js/api-reference/OperationHeader.tsx resources/js/api-reference/OperationView.tsx
git commit -m "feat: copy operations as Markdown"
```

### Task 4: Build normalized requests and extensible snippets

**Files:**
- Create: `resources/js/api-reference/request-state.ts`
- Create: `resources/js/api-reference/request-state.test.ts`
- Create: `resources/js/api-reference/request-builder.ts`
- Create: `resources/js/api-reference/request-builder.test.ts`
- Create: `resources/js/api-reference/snippets/types.ts`
- Create: `resources/js/api-reference/snippets/curl.ts`
- Create: `resources/js/api-reference/snippets/javascript.ts`
- Create: `resources/js/api-reference/snippets/snippets.test.ts`

**Interfaces:**

```ts
export type RequestValues = {
    parameters: Record<string, string>;
    mediaType: string | null;
    body: string;
};

export type RequestErrors = {
    parameters: Record<string, string>;
    body: string | null;
    request: string | null;
};

export type BuiltRequest = {
    method: string;
    url: string;
    headers: Record<string, string>;
    body: string | null;
};

export type BuildRequestResult =
    | { request: BuiltRequest; errors: null }
    | { request: null; errors: RequestErrors };

export type SnippetTemplate = {
    id: "curl" | "javascript";
    label: string;
    generate(request: BuiltRequest): string;
};
```

- [ ] **Step 1: Add failing state-initialization tests**

Cover:

- Direct parameter example, schema example/default/enum, then empty string.
- First JSON-compatible request contract selection.
- Pretty-printed explicit/schema-derived JSON body.
- `application/problem+json` accepted; form data ignored.
- Stable parameter keys from `parameterKey(param): string`.

- [ ] **Step 2: Run request-state tests and verify failure**

Run: `npm test -- resources/js/api-reference/request-state.test.ts`

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement request-state initialization**

Export:

```ts
export function parameterKey(param: Param): string;
export function isJsonMediaType(mediaType: string | null): boolean;
export function jsonRequestContracts(operation: Operation): Contract[];
export function initialRequestValues(operation: Operation, components?: unknown): RequestValues;
```

- [ ] **Step 4: Run request-state tests**

Run: `npm test -- resources/js/api-reference/request-state.test.ts`

Expected: PASS.

- [ ] **Step 5: Add failing request-builder tests**

Cover:

- Encoded path substitution.
- Query encoding and omission of empty optional values.
- Required path/query/header errors.
- Scalar headers.
- Exact selected JSON `Content-Type`.
- Invalid/required JSON errors.
- Authorization injection only when a token exists.
- Rejection of forbidden headers including `Cookie`, `Host`, `Content-Length`, and `Origin`.
- Array/object parameters rejected as non-executable.

- [ ] **Step 6: Run request-builder tests and verify failure**

Run: `npm test -- resources/js/api-reference/request-builder.test.ts`

Expected: FAIL because the module does not exist.

- [ ] **Step 7: Implement request validation and construction**

Export:

```ts
export function buildRequest(input: {
    operation: Operation;
    baseUrl: string | null;
    values: RequestValues;
    token: string | null;
}): BuildRequestResult;

export function redactAuthorization(request: BuiltRequest): BuiltRequest;
```

`redactAuthorization` returns a clone and replaces any case-insensitive bearer authorization header with `Bearer <YOUR_TOKEN>`.

- [ ] **Step 8: Run request-builder tests**

Run: `npm test -- resources/js/api-reference/request-builder.test.ts`

Expected: PASS.

- [ ] **Step 9: Add failing exact-output snippet tests**

Assert:

- cURL shell-quotes method, URL, headers, and JSON body safely.
- JavaScript uses `fetch(url, { method, headers, body })` with JSON-stringified literals.
- GET without headers/body stays compact.
- Both templates receive `redactAuthorization(request)`; neither output contains the fixture real token.

- [ ] **Step 10: Run snippet tests and verify failure**

Run: `npm test -- resources/js/api-reference/snippets/snippets.test.ts`

Expected: FAIL because the snippet modules do not exist.

- [ ] **Step 11: Implement the snippet templates**

`curl.ts` exports `curlSnippet: SnippetTemplate`.

`javascript.ts` exports `javascriptSnippet: SnippetTemplate`.

Each generator is deterministic and ends without trailing whitespace. Use a dedicated single-quote shell escaping helper for cURL and `JSON.stringify` for JavaScript literals.

- [ ] **Step 12: Run all focused tests and typecheck**

Run: `npm test -- resources/js/api-reference/request-state.test.ts resources/js/api-reference/request-builder.test.ts resources/js/api-reference/snippets/snippets.test.ts && npm run typecheck`

Expected: PASS.

- [ ] **Step 13: Commit**

```bash
git add resources/js/api-reference/request-state.ts resources/js/api-reference/request-state.test.ts resources/js/api-reference/request-builder.ts resources/js/api-reference/request-builder.test.ts resources/js/api-reference/snippets
git commit -m "feat: generate executable request snippets"
```

### Task 5: Execute and normalize browser responses

**Files:**
- Create: `resources/js/api-reference/execute-request.ts`
- Create: `resources/js/api-reference/execute-request.test.ts`

**Interfaces:**

```ts
export type ExecutedResponse = {
    kind: "response";
    status: number;
    statusText: string;
    durationMs: number;
    headers: Array<[string, string]>;
    body: string;
    contentType: string | null;
};

export type ExecutionError = {
    kind: "error";
    message: string;
};

export async function executeRequest(
    request: BuiltRequest,
    signal: AbortSignal,
    now?: () => number,
): Promise<ExecutedResponse | ExecutionError>;
```

- [ ] **Step 1: Add failing executor tests**

Mock `fetch` and cover:

- Method, headers, body, and signal forwarding.
- JSON pretty-printing.
- Text and malformed JSON fallback.
- Empty 204 response.
- 404/500 returned as `kind: "response"`.
- Sorted response-header tuples.
- Deterministic duration using an injected `now`.
- Generic network message that never includes the URL, request headers, body, or token.
- `AbortError` rethrown so the caller can silently ignore superseded requests.

- [ ] **Step 2: Run executor tests and verify failure**

Run: `npm test -- resources/js/api-reference/execute-request.test.ts`

Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement executor normalization**

Call:

```ts
fetch(request.url, {
    method: request.method,
    headers: request.headers,
    body: request.body,
    signal,
});
```

Read the body once with `response.text()`. Pretty-print when the body parses as JSON, regardless of an inaccurate content type. Return `"Request failed. Check the browser console and CORS configuration."` for transport failures without interpolating caught error text.

- [ ] **Step 4: Run focused tests and typecheck**

Run: `npm test -- resources/js/api-reference/execute-request.test.ts && npm run typecheck`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/api-reference/execute-request.ts resources/js/api-reference/execute-request.test.ts
git commit -m "feat: execute API requests in the browser"
```

### Task 6: Build the Lattice-composed request playground

**Files:**
- Create: `resources/js/api-reference/RequestPlayground.tsx`
- Create: `resources/js/api-reference/SnippetPanel.tsx`
- Create: `resources/js/api-reference/LiveResponsePanel.tsx`
- Create: `resources/js/api-reference/RequestPlayground.browser.test.tsx`
- Modify: `resources/js/api-reference/OperationView.tsx`
- Modify: `resources/js/api-reference/ApiReference.tsx`

**Interfaces:**

```ts
export type RequestPlaygroundProps = {
    operation: Operation;
    baseUrl: string | null;
    token: string | null;
    components: unknown;
};
```

`ApiReferenceProps` gains `token?: string | null`, and every `OperationView` call receives it.

- [ ] **Step 1: Add a failing browser test for rendering and live snippets**

Render a playground with path, enum query, boolean header, and JSON body inputs. Assert:

- Lattice-labelled controls render.
- cURL is selected initially.
- Editing path/query/body updates the snippet.
- Selecting JavaScript changes the snippet.
- The snippet contains `<YOUR_TOKEN>`, never the real token.
- Copy uses the selected snippet.

- [ ] **Step 2: Run the browser test and verify failure**

Run: `npm run test:browser -- resources/js/api-reference/RequestPlayground.browser.test.tsx`

Expected: FAIL because the component does not exist.

- [ ] **Step 3: Implement focused playground components**

Keep state, parameter fields, body selection, validation focus, and execution orchestration in `RequestPlayground.tsx`. Put these presentational units in focused files:

- `SnippetPanel.tsx`: language selection, read-only code, and copy action.
- `LiveResponsePanel.tsx`: response/error status, duration, headers, and body.

Use only Lattice `Card`, `CardHeader`, `CardTitle`, `CardContent`, `Button`, `CopyButton`, `Input`, `Label`, `NativeSelect`, `SegmentedPills`, `Textarea`, `Spinner`, and `Badge`.

- [ ] **Step 4: Add failing validation/focus browser tests**

Assert:

- Required missing values show errors without calling `fetch`.
- Invalid JSON remains in the textarea and shows an error.
- The first invalid input receives focus.
- Array/object and non-JSON request data are documented outside the playground but not rendered as controls.

- [ ] **Step 5: Implement validation presentation**

Call `buildRequest` on every state change to generate the snippet and again before execution. Render parameter/body errors with stable ids and `aria-describedby`. On execution validation failure, focus the element whose `data-field-key` matches the first error.

- [ ] **Step 6: Add failing execution browser tests**

Stub `fetch` and assert:

- “Try it out” enters loading state and disables itself.
- Success displays a live label, status badge, duration, headers, and body.
- HTTP 422 displays as a response.
- Network failure displays the generic safe message.
- Starting a second request aborts the first.
- Unmount aborts the active request.
- Changing inputs preserves the last live result.

- [ ] **Step 7: Implement abort-safe execution**

Store the active `AbortController` in a ref. Abort before starting, abort on cleanup, and ignore `AbortError`. Do not clear the previous result when form values change.

- [ ] **Step 8: Integrate the playground**

Add `token?: string | null` to `OperationViewProps`.

Render:

```tsx
<RequestBodySection requests={operation.requests} components={components} expandDepth={expandDepth} />
<RequestPlayground
    operation={operation}
    baseUrl={baseUrl ?? null}
    token={token ?? null}
    components={components}
/>
<ResponsesSection responses={operation.responses} components={components} expandDepth={expandDepth} />
```

Pass `token` from `ApiReference` in single-operation, stacked, and sidebar modes.

- [ ] **Step 9: Run browser, Node, and type checks**

Run: `npm run test:browser && npm test && npm run typecheck`

Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add resources/js/api-reference/RequestPlayground.tsx resources/js/api-reference/SnippetPanel.tsx resources/js/api-reference/LiveResponsePanel.tsx resources/js/api-reference/RequestPlayground.browser.test.tsx resources/js/api-reference/OperationView.tsx resources/js/api-reference/ApiReference.tsx
git commit -m "feat: add interactive API request playground"
```

### Task 7: Add PHP token configuration and workbench verification coverage

**Files:**
- Modify: `src/Doc/Lattice/ApiReference.php`
- Modify: `tests/Unit/ApiReferenceComponentTest.php`
- Modify: `workbench/app/Pages/ApiReferencePage.php`
- Modify: `workbench/app/Http/Controllers/StoreUserController.php`
- Modify: `workbench/fixtures/openapi.json`

**Interfaces:**
- Produces: `ApiReference::token(string $token): static`
- Produces: nullable serialized `token` prop

- [ ] **Step 1: Add failing Pest tests**

```php
it('defaults the request token to null', function (): void {
    $props = ApiReference::make()->jsonSerialize()['props'];

    expect($props['token'])->toBeNull();
});

it('sets the bearer token used by the request playground', function (): void {
    $props = ApiReference::make()->token('secret-token')->jsonSerialize()['props'];

    expect($props['token'])->toBe('secret-token');
});
```

- [ ] **Step 2: Run the focused test and verify failure**

Run: `./vendor/bin/pest tests/Unit/ApiReferenceComponentTest.php --filter=token`

Expected: FAIL because `token` and `token()` do not exist.

- [ ] **Step 3: Implement the fluent token property**

```php
public ?string $token = null;

public function token(string $token): static
{
    $this->token = $token;

    return $this;
}
```

- [ ] **Step 4: Exercise the property and a request header in the workbench**

Use an environment-only demo token without committing a credential:

```php
ApiReference::make()
    ->spec($document)
    ->token((string) config('services.spectacular.demo_token', 'workbench-token'))
```

Add a documented request header to `StoreUserController`:

```php
use Dedoc\Scramble\Attributes\HeaderParameter;

#[HeaderParameter(
    name: 'X-Debug-Context',
    description: 'Optional context echoed in request diagnostics.',
    required: false,
    type: 'string',
    example: 'docs',
)]
public function __invoke(Request $request): UserResource
```

Run `php artisan spectacular:openapi` and verify the fixture adds exactly that request header while retaining the existing path/query parameters and JSON request body.

- [ ] **Step 5: Format and run focused PHP tests**

Run:

```bash
vendor/bin/pint --dirty --format agent
./vendor/bin/pest tests/Unit/ApiReferenceComponentTest.php
```

Expected: PASS.

- [ ] **Step 6: Run frontend token-redaction tests**

Run:

```bash
npm test -- resources/js/api-reference/request-builder.test.ts resources/js/api-reference/snippets/snippets.test.ts
npm run test:browser -- resources/js/api-reference/RequestPlayground.browser.test.tsx
```

Expected: PASS; no output contains `secret-token` or `workbench-token`.

- [ ] **Step 7: Commit**

```bash
git add src/Doc/Lattice/ApiReference.php tests/Unit/ApiReferenceComponentTest.php workbench/app/Pages/ApiReferencePage.php workbench/app/Http/Controllers/StoreUserController.php workbench/fixtures/openapi.json
git commit -m "feat: configure API playground bearer tokens"
```

### Task 8: Full verification and consumer-facing review

**Files:**
- Verify: all files changed by Tasks 1–7

**Interfaces:**
- Verifies the complete feature and consumer bundle.

- [ ] **Step 1: Run all frontend gates**

Run:

```bash
npm run typecheck
npm test
npm run build
```

Expected: all Node and Chromium tests PASS; TypeScript emits no errors; Vite build succeeds.

- [ ] **Step 2: Run the full backend/CI mirror**

Run: `composer check`

Expected: Pint, PHPStan, and Pest PASS.

- [ ] **Step 3: Inspect generated fixture drift**

Run:

```bash
git diff -- workbench/fixtures/openapi.json
php artisan spectacular:openapi
git diff -- workbench/fixtures/openapi.json
```

Expected: no unexpected drift. Any intentional diff must be explained in the eventual PR.

- [ ] **Step 4: Manually verify the workbench**

Run: `composer serve`

Open the resolved `/docs` URL and verify:

1. Copy Markdown includes only the selected operation and no live values/token.
2. Path/query/header edits update cURL and JavaScript immediately.
3. Both snippets use `<YOUR_TOKEN>`.
4. Invalid JSON blocks execution.
5. A same-origin public operation executes and displays status, duration, headers, and body.
6. HTTP error responses render as responses.
7. The browser console has no errors.
8. Sidebar and stacked layouts remain usable at desktop and narrow widths.

- [ ] **Step 5: Review reuse and altitude**

Confirm:

- No new local primitive duplicates Lattice.
- `OperationView` remains an orchestrator.
- Token redaction happens before every snippet.
- Pure modules do not import React.
- Browser-only logic stays in the executor/playground.
- No obsolete or narrating comments were added.

- [ ] **Step 6: Record final state**

Run:

```bash
git status --short
git log --oneline --decorate -10
```

Expected: clean worktree with meaningful conventional commits and no agent attribution.
