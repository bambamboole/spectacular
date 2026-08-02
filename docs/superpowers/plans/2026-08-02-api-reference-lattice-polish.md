# API Reference Lattice Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the client-side `$ref` resolution crash that breaks response/request body rendering for real consumers of the API reference viewer, and replace every hand-rolled UI control in it with the equivalent primitive `@lattice-php/lattice` already ships, so the viewer looks and behaves like the rest of a Lattice app.

**Architecture:** No new components or files beyond one shared color-mapping helper. All work is inside `resources/js/api-reference/`, `resources/js/schema/`, `resources/js/plugin.ts`, and `workbench/resources/js/`. Nine sequential tasks: ship the Buffer polyfill from the actual entry point (with the distribution caveat documented), parse and render response headers, then a component-by-component swap of hand-rolled elements for `@lattice-php/lattice/ui` primitives.

**Tech Stack:** React 19, TypeScript, Vitest, `@lattice-php/lattice` ^0.36 (`ui`, `icons`, `core/types`, `types/generated` subpath exports).

## Global Constraints

- Design doc: `docs/superpowers/specs/2026-08-02-api-reference-lattice-polish-design.md` — read it before starting; every task below implements one of its sections.
- No new layout/IA changes, no changes to the OpenAPI/AsyncAPI generators, no visual redesign beyond swapping hand-rolled elements for existing Lattice components.
- This repo has no component-rendering test harness (no `@testing-library/react`, only Vitest over pure functions) — do not add one. UI-swap tasks are verified by `npm run typecheck` plus a manual browser check against the running workbench; only pure-function changes (parsing, color mapping) get Vitest cases, matching the project's existing convention (`resources/js/api-reference/parse.test.ts`, `resources/js/schema/build-rows.test.ts`).
- Run `npm run typecheck` and `npm test` at the end of every task. Run `composer test` only for tasks touching PHP (none in this plan — it's all `resources/js`).

---

### Task 1: Ship the Buffer polyfill, document required npm packages

**Files:**
- Modify: `resources/js/plugin.ts`
- Modify: `package.json` (root)
- Modify: `README.md`
- Delete: `workbench/resources/js/polyfills.ts`
- Modify: `workbench/resources/js/app.tsx`

**Interfaces:**
- Produces: no new exports. `resources/js/plugin.ts`'s default export (`createPlugin(...)`) is unchanged in shape — only a module-level side effect is added above it.

- [ ] **Step 1: Add the Buffer shim to the real entry point**

Read the current file first:

```bash
cat resources/js/plugin.ts
```

It currently reads:

```ts
import { createPlugin, lazyComponent } from "@lattice-php/lattice";

export default createPlugin({
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
});
```

Replace it with:

```ts
import { Buffer } from "buffer";
import { createPlugin, lazyComponent } from "@lattice-php/lattice";

const globalWithBuffer = globalThis as typeof globalThis & { Buffer?: typeof Buffer };

// @apidevtools/json-schema-ref-parser reaches for the Node `Buffer` global, which
// the browser bundle does not provide. Set it before the lazily-loaded viewer (and
// everything it imports) ever evaluates.
globalWithBuffer.Buffer = globalWithBuffer.Buffer ?? Buffer;

export default createPlugin({
    name: "spectacular",
    components: {
        "spectacular.api-reference": lazyComponent(
            () => import("./api-reference/ApiReference"),
        ),
    },
});
```

`plugin.ts` is imported eagerly by any app registering the component (that's how Lattice's Vite plugin discovers it via `extra.lattice.plugin` in `composer.json`), so the shim is guaranteed to run before the lazy `ApiReference` chunk loads.

- [ ] **Step 2: Move `buffer` to a real `dependencies` entry**

`buffer` is currently a `devDependency` of this repo's own `package.json` — that was fine when only the workbench used it, but `plugin.ts` (the shipped entry point) now imports it too. Read the current file:

```bash
cat package.json
```

Change the `"devDependencies"` block: remove the `"buffer": "^6.0.3"` line from it, and add a `"dependencies"` block above `"devDependencies"` containing it:

```json
{
    "private": true,
    "type": "module",
    "scripts": {
        "dev": "vite",
        "build": "vite build",
        "typecheck": "tsc --noEmit",
        "test": "vitest run"
    },
    "dependencies": {
        "buffer": "^6.0.3"
    },
    "devDependencies": {
        "@apidevtools/json-schema-ref-parser": "^15.0.0",
        "@inertiajs/react": "^3.0.0",
        "@laravel/echo-react": "^2.0.0",
        "@lattice-php/lattice": "^0.36.0",
        "@stoplight/json-schema-tree": "^4.0.0",
        "@tailwindcss/vite": "^4.1.11",
        "@types/react": "^19.0.0",
        "@types/react-dom": "^19.0.0",
        "@vitejs/plugin-react": "^5.0.0",
        "laravel-vite-plugin": "^3.0.0",
        "react": "^19.2.0",
        "react-dom": "^19.2.0",
        "tailwindcss": "^4.0.0",
        "typescript": "^5.6.0",
        "vite": "^8.0.0",
        "vitest": "^3.0.0"
    }
}
```

Run `npm install` afterward so `package-lock.json` reflects the move (it's gitignored per `.gitignore`, so this is just for your local state).

- [ ] **Step 3: Delete the now-redundant workbench polyfill**

```bash
rm workbench/resources/js/polyfills.ts
```

Read `workbench/resources/js/app.tsx`:

```bash
cat workbench/resources/js/app.tsx
```

It starts with:

```tsx
import "./polyfills";
import "../css/app.css";
```

Remove the `import "./polyfills";` line — the shim now lives in `resources/js/plugin.ts`, which `app.tsx` already imports transitively via `spectacularPlugin`. The file should start:

```tsx
import "../css/app.css";
```

- [ ] **Step 4: Document the npm packages a consumer needs**

This is not a fully self-contained fix: `bambamboole/spectacular` ships raw TSX source through Composer with no npm-publish step, so nothing here can install a package into a consumer's `node_modules`. Document what's required. Read the README section added previously:

```bash
grep -n "Displaying docs with Lattice" README.md
```

Immediately after the existing `ApiReference`/`Cache::rememberForever` code example in that section (before the "Scramble's generator only needs..." paragraph), insert this paragraph and code block into `README.md` (this is literal content to add to the file, not a plan code sample — copy it in directly):

> The viewer's React component has its own npm dependencies, which your app's `package.json` needs alongside
> `@lattice-php/lattice`:
>
> ```bash
> npm install @apidevtools/json-schema-ref-parser @stoplight/json-schema-tree buffer
> ```
>
> `@apidevtools/json-schema-ref-parser` resolves `$ref`s when rendering a schema tree, `@stoplight/json-schema-tree`
> turns the dereferenced schema into a tree the viewer renders, and `buffer` polyfills a Node global the ref-parser
> package expects that browsers don't provide. Missing one of these surfaces as a build-time "cannot resolve module"
> error rather than a broken viewer.

(Drop the `>` blockquote markers when pasting into `README.md` — they're here only to visually set the inserted text apart from this plan's own instructions.)

- [ ] **Step 5: Verify**

```bash
npm run typecheck
npm test
npm run build
```

All three must succeed. `npm run build` succeeding confirms `import { Buffer } from "buffer"` resolves now that `buffer` is a real dependency.

- [ ] **Step 6: Manual regression check**

```bash
composer serve
```

In a browser, open `http://127.0.0.1:8000/docs`, select the `GET /categories` operation (or any operation with a response body), and open the browser's devtools console. Confirm:
- No `ReferenceError: Buffer is not defined` in the console.
- The response body panel shows the actual resolved schema fields (e.g., `id`, `name`), not an error message.

Stop the server (`Ctrl+C`) when done.

- [ ] **Step 7: Commit**

```bash
git add resources/js/plugin.ts package.json package-lock.json README.md workbench/resources/js/app.tsx
git rm workbench/resources/js/polyfills.ts
git commit -m "fix: ship the Buffer polyfill from the real plugin entry point"
```

(If `package-lock.json` is gitignored in this repo, `git add` will simply no-op on it — that's expected.)

---

### Task 2: Parse response headers

**Files:**
- Modify: `resources/js/api-reference/types.ts`
- Modify: `resources/js/api-reference/parse.ts`
- Modify: `resources/js/api-reference/parse.test.ts`

**Interfaces:**
- Consumes: nothing new from Task 1.
- Produces: `Contract` (in `types.ts`) gains `headers: Param[]`. Both `buildRequests` and `buildResponses` (in `parse.ts`) populate it (empty array for requests, parsed for responses). `resolveRef`'s `kind` parameter gains a `"headers"` member. Task 3 consumes `Contract.headers`.

- [ ] **Step 1: Add `headers` to the `Contract` type**

Read `resources/js/api-reference/types.ts`. Change:

```ts
export type Contract = {
    role: "request" | "response";
    status: string | null;
    mediaType: string | null;
    schema: unknown;
    title: string | null;
    examples: ContractExample[];
};
```

to:

```ts
export type Contract = {
    role: "request" | "response";
    status: string | null;
    mediaType: string | null;
    schema: unknown;
    title: string | null;
    examples: ContractExample[];
    headers: Param[];
};
```

- [ ] **Step 2: Write the failing tests**

Read `resources/js/api-reference/parse.test.ts` in full first — it defines a shared `spec` fixture used by most tests, plus several self-contained specs for feature-specific tests. Add a new `describe` block at the end of the file (after the `"effective security resolution"` block):

```ts
describe("response headers", () => {
    it("extracts response headers into Param entries", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Headers API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        get: {
                            operationId: "getWidgets",
                            responses: {
                                "200": {
                                    description: "OK",
                                    content: { "application/json": { schema: { type: "object" } } },
                                    headers: {
                                        "X-RateLimit-Limit": {
                                            description: "Requests allowed per window",
                                            schema: { type: "integer" },
                                        },
                                        "X-RateLimit-Remaining": {
                                            required: true,
                                            schema: { type: "integer" },
                                        },
                                    },
                                },
                            },
                        },
                    },
                },
            },
            "get-widgets",
        )!;

        expect(op.responses[0].headers).toEqual([
            {
                name: "X-RateLimit-Limit",
                location: "header",
                required: false,
                deprecated: false,
                description: "Requests allowed per window",
                schema: { type: "integer" },
            },
            {
                name: "X-RateLimit-Remaining",
                location: "header",
                required: true,
                deprecated: false,
                description: null,
                schema: { type: "integer" },
            },
        ]);
    });

    it("resolves a $ref response header from components.headers", () => {
        const op = parseOperation(
            {
                openapi: "3.0.0",
                info: { title: "Headers API", version: "1.0.0", description: null },
                paths: {
                    "/widgets": {
                        get: {
                            operationId: "getWidgets",
                            responses: {
                                "200": {
                                    description: "OK",
                                    headers: {
                                        "X-Request-Id": { $ref: "#/components/headers/RequestId" },
                                    },
                                },
                            },
                        },
                    },
                },
                components: {
                    headers: {
                        RequestId: { description: "Correlates logs to this request", schema: { type: "string" } },
                    },
                },
            },
            "get-widgets",
        )!;

        expect(op.responses[0].headers).toEqual([
            {
                name: "X-Request-Id",
                location: "header",
                required: false,
                deprecated: false,
                description: "Correlates logs to this request",
                schema: { type: "string" },
            },
        ]);
    });

    it("returns an empty array when a response has no headers", () => {
        const op = parseOperation(spec, "get-users-id")!;

        expect(op.responses[0].headers).toEqual([]);
    });
});
```

Also update the four existing `toEqual` assertions in this file that check a full `Contract` object literal — they'll now fail because the actual objects carry a `headers` field the expected literals don't. Add `headers: []` to each:

1. In `"builds the request Contract from requestBody"`, the expected object gains `headers: []` after `examples: []`.
2. In `"builds a response Contract per status/mediaType, resolving a $ref response"`, both expected objects (status `200` and `404`) gain `headers: []` after their `examples: []`.
3. In `"produces a bodyless response Contract with a null schema"`, the expected object gains `headers: []` after `examples: []`.
4. In `"resolves a $ref requestBody and builds the request Contract"`, the expected object gains `headers: []` after `examples: []`.

- [ ] **Step 3: Run tests to verify the new ones fail**

```bash
npm test -- parse.test
```

Expected: the three new `"response headers"` tests fail (`headers` is `undefined`, not an array — `buildResponses`/`buildRequests` don't produce it yet). The four updated existing tests also fail for the same reason until Step 4 lands.

- [ ] **Step 4: Implement header parsing**

Read `resources/js/api-reference/parse.ts` in full. Three changes:

**4a.** Widen `resolveRef`'s `kind` parameter to include `"headers"`:

```ts
function resolveRef<T>(spec: any, ref: string | undefined, kind: "parameters" | "requestBodies" | "responses" | "examples" | "headers"): T | null {
```

**4b.** Widen the inline `responses` type on `RawOperation` to carry `headers`:

```ts
type RawOperation = {
    operationId?: string;
    summary?: string;
    description?: string | null;
    tags?: string[];
    deprecated?: boolean;
    parameters?: RawParameter[];
    requestBody?: { $ref?: string; description?: string | null; content?: Record<string, RawMediaTypeObject> };
    responses?: Record<string, { $ref?: string; description?: string | null; content?: Record<string, RawMediaTypeObject>; headers?: Record<string, RawParameter> }>;
    security?: Array<Record<string, string[]>>;
};
```

**4c.** Add a `buildResponseHeaders` function right after `buildParam` (which it reuses):

```ts
function buildResponseHeaders(spec: any, headers: Record<string, RawParameter> | undefined): Param[] {
    if (!headers) return [];

    return Object.entries(headers).map(([name, header]) => {
        const resolved = header.$ref
            ? (resolveRef<RawParameter>(spec, header.$ref, "headers") ?? header)
            : header;

        return buildParam({ ...resolved, name, in: "header" });
    });
}
```

**4d.** In `buildRequests`, add `headers: []` to the returned object (request bodies don't carry headers in OpenAPI):

```ts
function buildRequests(spec: any, requestBody: RawOperation["requestBody"]): Contract[] {
    if (!requestBody) return [];

    const resolved = requestBody.$ref
        ? (resolveRef<NonNullable<RawOperation["requestBody"]>>(spec, requestBody.$ref, "requestBodies") ?? requestBody)
        : requestBody;

    const content = resolved.content ?? {};
    const title = resolved.description ?? null;

    return Object.entries(content).map(([mediaType, mediaTypeObject]) => ({
        role: "request" as const,
        status: null,
        mediaType,
        schema: mediaTypeObject?.schema ?? null,
        title,
        examples: buildExamples(spec, mediaTypeObject),
        headers: [],
    }));
}
```

**4e.** In `buildResponses`, compute the header `Param[]` once per status and attach it to every contract for that status:

```ts
function buildResponses(spec: any, responses: RawOperation["responses"]): Contract[] {
    if (!responses) return [];

    const contracts: Contract[] = [];

    for (const [status, response] of Object.entries(responses)) {
        const resolved = response.$ref
            ? (resolveRef<NonNullable<typeof response>>(spec, response.$ref, "responses") ?? response)
            : response;

        const title = resolved.description ?? null;
        const content = resolved.content ?? {};
        const mediaTypes = Object.entries(content);
        const headers = buildResponseHeaders(spec, resolved.headers);

        if (mediaTypes.length === 0) {
            contracts.push({ role: "response", status, mediaType: null, schema: null, title, examples: [], headers });
            continue;
        }

        for (const [mediaType, mediaTypeObject] of mediaTypes) {
            contracts.push({
                role: "response",
                status,
                mediaType,
                schema: mediaTypeObject?.schema ?? null,
                title,
                examples: buildExamples(spec, mediaTypeObject),
                headers,
            });
        }
    }

    return contracts;
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
npm test -- parse.test
npm run typecheck
```

Expected: all tests pass, no type errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/api-reference/types.ts resources/js/api-reference/parse.ts resources/js/api-reference/parse.test.ts
git commit -m "feat: parse response headers from the OpenAPI document"
```

---

### Task 3: Render response headers

**Files:**
- Modify: `resources/js/api-reference/OperationView.tsx`

**Interfaces:**
- Consumes: `Contract.headers: Param[]` (Task 2), existing `ParamRow` component (defined earlier in this same file).
- Produces: no new exports.

- [ ] **Step 1: Render headers in `ResponsesSection`**

Read `resources/js/api-reference/OperationView.tsx` in full (you'll need the existing `ParamRow` component's exact shape below). Find `ResponsesSection`:

```tsx
function ResponsesSection({
    responses,
    components,
    expandDepth,
}: {
    responses: Contract[];
    components: unknown;
    expandDepth: number;
}): React.ReactNode {
    const [active, setActive] = useState(0);

    if (responses.length === 0) return null;

    const current = responses[active] ?? responses[0];

    return (
        <section>
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Responses</h2>
            <div className="mb-3 flex flex-wrap gap-1 border-b border-lt-border pb-2">
                {responses.map((response, index) => (
                    <button
                        key={`${response.status ?? "default"}-${response.mediaType ?? "none"}-${index}`}
                        type="button"
                        onClick={() => setActive(index)}
                        aria-pressed={index === active}
                        className={`rounded-lt-sm px-2 py-1 text-xs transition-colors ${
                            index === active
                                ? "bg-lt-primary text-lt-primary-fg"
                                : "bg-lt-muted text-lt-muted-fg hover:bg-lt-accent hover:text-lt-accent-fg"
                        }`}
                    >
                        {contractLabel(response)}
                    </button>
                ))}
            </div>
            {current ? (
                <div>
                    {current.title ? <p className="mb-2 text-sm text-lt-muted-fg">{current.title}</p> : null}
                    {current.schema || current.examples.length > 0 ? (
                        <SchemaExampleView
                            key={contractLabel(current)}
                            schema={current.schema}
                            examples={current.examples}
                            components={components}
                            noSchemaMessage="No response body."
                            expandDepth={expandDepth}
                        />
                    ) : (
                        <p className="text-sm text-lt-muted-fg">No response body.</p>
                    )}
                </div>
            ) : null}
        </section>
    );
}
```

Leave the tab-button block as-is for now (Task 4 replaces it) — only add the headers block, right after the `current.title` paragraph and before the schema/examples block:

```tsx
            {current ? (
                <div>
                    {current.title ? <p className="mb-2 text-sm text-lt-muted-fg">{current.title}</p> : null}
                    {current.headers.length > 0 ? (
                        <div className="mb-4">
                            <h3 className="mb-1 text-xs font-semibold uppercase tracking-wide text-lt-muted-fg">
                                Response headers
                            </h3>
                            <ul>
                                {current.headers.map((header) => (
                                    <ParamRow key={header.name} param={header} />
                                ))}
                            </ul>
                        </div>
                    ) : null}
                    {current.schema || current.examples.length > 0 ? (
```

(`ParamGroupSection`'s auto-generated `"{location} parameters"` heading doesn't read well for headers — `ParamRow` is reused directly under a purpose-written "Response headers" heading instead.)

- [ ] **Step 2: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 3: Manual check**

```bash
composer serve
```

Response headers aren't present in the workbench fixture yet, so there's nothing to visually confirm beyond "nothing broke" — open `http://127.0.0.1:8000/docs`, select any operation, confirm the Responses section still renders exactly as before (status tabs, then body). Stop the server when done.

- [ ] **Step 4: Commit**

```bash
git add resources/js/api-reference/OperationView.tsx
git commit -m "feat: render response headers in the API reference viewer"
```

---

### Task 4: Adopt SegmentedPills for the response and schema/example tabs

**Files:**
- Modify: `resources/js/api-reference/OperationView.tsx`

**Interfaces:**
- Consumes: `SegmentedPills` from `@lattice-php/lattice/ui` (`{ariaLabel?, autoFocus?, disabled?, name, onSelect, options, tabIndex?, value}`), `Option` type from `@lattice-php/lattice/core/types` (`{data: Record<string, unknown> | null, label: string, value: string}`).
- Produces: no new exports.

- [ ] **Step 1: Add the imports**

At the top of `resources/js/api-reference/OperationView.tsx`, add:

```tsx
import { SegmentedPills } from "@lattice-php/lattice/ui";
import type { Option } from "@lattice-php/lattice/core/types";
```

- [ ] **Step 2: Replace the Schema/Example toggle**

Find `SchemaExampleView`:

```tsx
function SchemaExampleView({
    schema,
    examples,
    components,
    noSchemaMessage,
    expandDepth,
}: {
    schema: unknown;
    examples: ContractExample[];
    components: unknown;
    noSchemaMessage: string;
    expandDepth: number;
}): React.ReactNode {
    const [tab, setTab] = useState<SchemaTab>("schema");
    const [selected, setSelected] = useState(0);

    if (examples.length === 0) {
        return <SchemaView schema={schema} components={components} expandDepth={expandDepth} />;
    }

    const current = examples[selected] ?? examples[0];

    return (
        <div>
            <div className="mb-2 flex flex-wrap gap-1 border-b border-lt-border pb-2">
                {SCHEMA_TABS.map(({ key, label }) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        aria-pressed={tab === key}
                        className={`rounded-lt-sm px-2 py-1 text-xs transition-colors ${
                            tab === key
                                ? "bg-lt-primary text-lt-primary-fg"
                                : "bg-lt-muted text-lt-muted-fg hover:bg-lt-accent hover:text-lt-accent-fg"
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>
```

Replace just the tab-button `<div>` block with:

```tsx
            <div className="mb-2 pb-2">
                <SegmentedPills
                    name="schema-example-tab"
                    ariaLabel="Schema or example"
                    options={SCHEMA_TABS.map(({ key, label }) => ({ label, value: key, data: null }))}
                    value={tab}
                    onSelect={(value) => setTab(value as SchemaTab)}
                />
            </div>
```

The rest of `SchemaExampleView` (the `tab === "schema" ? ... : ...` block below it) is unchanged.

- [ ] **Step 3: Replace the response status selector**

Find `ResponsesSection` (as modified in Task 3) and replace its `useState(0)` index-based selection with string-key-based selection, and its button group with `SegmentedPills`:

```tsx
function ResponsesSection({
    responses,
    components,
    expandDepth,
}: {
    responses: Contract[];
    components: unknown;
    expandDepth: number;
}): React.ReactNode {
    if (responses.length === 0) return null;

    const [activeLabel, setActiveLabel] = useState<string>(contractLabel(responses[0]));
    const current = responses.find((response) => contractLabel(response) === activeLabel) ?? responses[0];
    const options: Option[] = responses.map((response) => ({
        label: contractLabel(response),
        value: contractLabel(response),
        data: null,
    }));

    return (
        <section>
            <h2 className="mb-2 text-sm font-semibold text-lt-fg">Responses</h2>
            <div className="mb-3 pb-2">
                <SegmentedPills
                    name="response-status"
                    ariaLabel="Response status"
                    options={options}
                    value={activeLabel}
                    onSelect={setActiveLabel}
                />
            </div>
            {current ? (
```

The rest of the function (the headers block from Task 3, and the schema/examples block) is unchanged. Note the early `if (responses.length === 0) return null;` moved above the new `useState` call — React hooks can't run conditionally, and `responses[0]` in the `useState` initializer needs that guard to already have passed.

- [ ] **Step 4: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 5: Manual check**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs`, select `GET /categories`. Confirm:
- The response status selector (e.g. "200 application/json") renders as a rounded pill group matching Lattice's segmented-control look (compare visually to the `SchemaTab` Schema/Example toggle inside the response body, which now uses the same component).
- Clicking a different status pill switches the shown response.
- If an operation has request/response examples, the Schema/Example toggle also renders as pills and switching works.

Stop the server when done.

- [ ] **Step 6: Commit**

```bash
git add resources/js/api-reference/OperationView.tsx
git commit -m "feat: use Lattice SegmentedPills for the response and schema/example tabs"
```

---

### Task 5: Adopt Badge for the method pill and deprecated markers

**Files:**
- Create: `resources/js/api-reference/http-method-color.ts`
- Create: `resources/js/api-reference/http-method-color.test.ts`
- Modify: `resources/js/api-reference/OperationView.tsx`
- Modify: `resources/js/api-reference/ApiReferenceNav.tsx`

**Interfaces:**
- Produces: `httpMethodColor(method: string): ColorName`, exported from `resources/js/api-reference/http-method-color.ts`. Consumed by both `OperationView.tsx` and `ApiReferenceNav.tsx`.
- Consumes: `Badge` from `@lattice-php/lattice/ui`, `ColorName` type from `@lattice-php/lattice/types/generated`.

**Scope note:** the design doc's component table also lists the required-parameter `*` marker (`ParamRow`, `OperationView.tsx`) as a `Badge` candidate. Wrapping a single `*` glyph in a full pill looks like a rendering glitch rather than an improvement, so it's left as the existing `<span className="text-lt-danger">*</span>` — this task only touches the method pill and the "deprecated" text markers, which read naturally as short-word badges.

- [ ] **Step 1: Write the failing test for the color helper**

Create `resources/js/api-reference/http-method-color.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import { httpMethodColor } from "./http-method-color";

describe("httpMethodColor", () => {
    it("maps GET to info", () => {
        expect(httpMethodColor("GET")).toBe("info");
    });

    it("maps POST to success", () => {
        expect(httpMethodColor("POST")).toBe("success");
    });

    it("maps PUT and PATCH to warning", () => {
        expect(httpMethodColor("PUT")).toBe("warning");
        expect(httpMethodColor("PATCH")).toBe("warning");
    });

    it("maps DELETE to danger", () => {
        expect(httpMethodColor("DELETE")).toBe("danger");
    });

    it("maps an unrecognized method to default", () => {
        expect(httpMethodColor("OPTIONS")).toBe("default");
    });
});
```

- [ ] **Step 2: Run it to verify it fails**

```bash
npm test -- http-method-color
```

Expected: FAIL — `./http-method-color` doesn't exist yet.

- [ ] **Step 3: Implement the helper**

Create `resources/js/api-reference/http-method-color.ts`:

```ts
import type { ColorName } from "@lattice-php/lattice/types/generated";

export function httpMethodColor(method: string): ColorName {
    switch (method) {
        case "GET":
            return "info";
        case "POST":
            return "success";
        case "PUT":
        case "PATCH":
            return "warning";
        case "DELETE":
            return "danger";
        default:
            return "default";
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

```bash
npm test -- http-method-color
```

Expected: PASS.

- [ ] **Step 5: Use it in `OperationView.tsx`**

Add the imports at the top of `resources/js/api-reference/OperationView.tsx`:

```tsx
import { Badge } from "@lattice-php/lattice/ui";
import { httpMethodColor } from "./http-method-color";
```

Find the operation header:

```tsx
            <header className="mb-6">
                <div className="flex items-center gap-2">
                    <span className="rounded-lt-xs bg-lt-primary px-2 py-0.5 text-xs font-semibold uppercase text-lt-primary-fg">
                        {operation.summary.method}
                    </span>
                    <span className="font-mono text-sm">
                        {baseUrl ? <span className="text-lt-muted-fg">{baseUrl}</span> : null}
                        <span className="text-lt-muted-fg">{operation.summary.path}</span>
                    </span>
                    {operation.summary.deprecated ? (
                        <span className="rounded-lt-xs bg-lt-danger px-2 py-0.5 text-xs text-lt-danger-fg">
                            deprecated
                        </span>
                    ) : null}
                </div>
```

Replace the method `<span>` and the deprecated `<span>`:

```tsx
            <header className="mb-6">
                <div className="flex items-center gap-2">
                    <Badge color={httpMethodColor(operation.summary.method)} className="text-xs font-semibold uppercase">
                        {operation.summary.method}
                    </Badge>
                    <span className="font-mono text-sm">
                        {baseUrl ? <span className="text-lt-muted-fg">{baseUrl}</span> : null}
                        <span className="text-lt-muted-fg">{operation.summary.path}</span>
                    </span>
                    {operation.summary.deprecated ? <Badge color="danger">deprecated</Badge> : null}
                </div>
```

- [ ] **Step 6: Use it in `ApiReferenceNav.tsx`**

Add the imports at the top of `resources/js/api-reference/ApiReferenceNav.tsx`:

```tsx
import { Badge } from "@lattice-php/lattice/ui";
import { httpMethodColor } from "./http-method-color";
```

Find the operation row button:

```tsx
                                        <button
                                            type="button"
                                            onClick={() => onSelect(id)}
                                            aria-current={active ? "page" : undefined}
                                            className={`flex w-full items-center gap-2 rounded-lt-sm px-2 py-1 text-left text-sm transition-colors ${
                                                active
                                                    ? "bg-lt-accent text-lt-accent-fg"
                                                    : "text-lt-fg hover:bg-lt-muted"
                                            }`}
                                        >
                                            <span className="font-mono text-xs text-lt-muted-fg">{summary.method}</span>
                                            <span className="truncate">{summary.path}</span>
                                            {summary.deprecated ? (
                                                <span className="ml-auto shrink-0 text-xs text-lt-danger">deprecated</span>
                                            ) : null}
                                        </button>
```

Replace the method and deprecated `<span>`s:

```tsx
                                        <button
                                            type="button"
                                            onClick={() => onSelect(id)}
                                            aria-current={active ? "page" : undefined}
                                            className={`flex w-full items-center gap-2 rounded-lt-sm px-2 py-1 text-left text-sm transition-colors ${
                                                active
                                                    ? "bg-lt-accent text-lt-accent-fg"
                                                    : "text-lt-fg hover:bg-lt-muted"
                                            }`}
                                        >
                                            <Badge color={httpMethodColor(summary.method)} className="text-xs">
                                                {summary.method}
                                            </Badge>
                                            <span className="truncate">{summary.path}</span>
                                            {summary.deprecated ? (
                                                <Badge color="danger" className="ml-auto shrink-0">
                                                    deprecated
                                                </Badge>
                                            ) : null}
                                        </button>
```

- [ ] **Step 7: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 8: Manual check**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs`. Confirm the method badges in the nav list and the operation header render as colored pills (blue/info for GET, matching across nav and header for the same operation), and that `POST /users` shows a green/success badge in both places. Stop the server when done.

- [ ] **Step 9: Commit**

```bash
git add resources/js/api-reference/http-method-color.ts resources/js/api-reference/http-method-color.test.ts resources/js/api-reference/OperationView.tsx resources/js/api-reference/ApiReferenceNav.tsx
git commit -m "feat: use Lattice Badge for method pills and deprecated markers"
```

---

### Task 6: Adopt CopyableText for the operation URL

**Files:**
- Modify: `resources/js/api-reference/OperationView.tsx`

**Interfaces:**
- Consumes: `CopyableText` from `@lattice-php/lattice/ui` (`{value: string, label: string, children?: ReactNode}`).

- [ ] **Step 1: Add the import**

At the top of `resources/js/api-reference/OperationView.tsx`, add `CopyableText` to the existing `@lattice-php/lattice/ui` import (from Task 5):

```tsx
import { Badge, CopyableText } from "@lattice-php/lattice/ui";
```

- [ ] **Step 2: Wrap the URL**

Find the header (as modified in Task 5):

```tsx
                    <span className="font-mono text-sm">
                        {baseUrl ? <span className="text-lt-muted-fg">{baseUrl}</span> : null}
                        <span className="text-lt-muted-fg">{operation.summary.path}</span>
                    </span>
```

Replace with:

```tsx
                    <CopyableText value={`${baseUrl ?? ""}${operation.summary.path}`} label="operation URL">
                        <span className="font-mono text-sm text-lt-muted-fg">
                            {baseUrl}
                            {operation.summary.path}
                        </span>
                    </CopyableText>
```

- [ ] **Step 3: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 4: Manual check**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs`, select any operation. Confirm a small copy button/icon appears next to the operation URL in the header, and clicking it copies the full URL (paste into a text field to confirm) and briefly shows a "copied" state. Stop the server when done.

- [ ] **Step 5: Commit**

```bash
git add resources/js/api-reference/OperationView.tsx
git commit -m "feat: add copy-to-clipboard for the operation URL"
```

---

### Task 7: Adopt Icon for the schema tree's expand/collapse caret

**Files:**
- Modify: `resources/js/schema/SchemaView.tsx`

**Interfaces:**
- Consumes: `Icon` from `@lattice-php/lattice/icons` (`{name: IconName} & React.ComponentProps<"svg">`). `"chevron-down"` is used internally by Lattice's own `Collapsible` component, so it's part of the guaranteed baseline icon set.

- [ ] **Step 1: Add the import**

At the top of `resources/js/schema/SchemaView.tsx`, add:

```tsx
import { Icon } from "@lattice-php/lattice/icons";
```

- [ ] **Step 2: Replace the caret glyphs**

Find:

```tsx
                {hasChildren ? (
                    <button type="button" onClick={() => setOpen((v) => !v)} className="text-lt-muted-fg">
                        {open ? "▾" : "▸"}
                    </button>
                ) : (
                    <span className="w-3" />
                )}
```

Replace with:

```tsx
                {hasChildren ? (
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="text-lt-muted-fg"
                        aria-expanded={open}
                    >
                        <Icon
                            name="chevron-down"
                            className={`size-lt-icon-xs transition-transform ${open ? "" : "-rotate-90"}`}
                        />
                    </button>
                ) : (
                    <span className="w-3" />
                )}
```

- [ ] **Step 3: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 4: Manual check**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs`, select `GET /categories`, expand the response schema tree. Confirm a chevron icon (not a `▾`/`▸` glyph) renders next to expandable rows, rotates on toggle, and matches the chevron style used elsewhere in Lattice (e.g. any collapsible section). Stop the server when done.

- [ ] **Step 5: Commit**

```bash
git add resources/js/schema/SchemaView.tsx
git commit -m "feat: use Lattice Icon for the schema tree expand/collapse caret"
```

---

### Task 8: Adopt Input and NativeSelect in the nav sidebar

**Files:**
- Modify: `resources/js/api-reference/ApiReferenceNav.tsx`

**Interfaces:**
- Consumes: `Input` from `@lattice-php/lattice/ui` (`React.ComponentProps<"input">`), `NativeSelect` from `@lattice-php/lattice/ui/native-select` (note: not re-exported from the `@lattice-php/lattice/ui` barrel — its own subpath) (`React.ComponentProps<"select">`).

- [ ] **Step 1: Add the imports**

At the top of `resources/js/api-reference/ApiReferenceNav.tsx`, add:

```tsx
import { Input } from "@lattice-php/lattice/ui";
import { NativeSelect } from "@lattice-php/lattice/ui/native-select";
```

- [ ] **Step 2: Replace the server `<select>`**

Find `ServerPicker`:

```tsx
    return (
        <select
            value={selectedServerUrl ?? ""}
            onChange={(event) => onServerChange(event.target.value)}
            aria-label="Select server"
            className="w-full rounded-lt-sm border border-lt-input bg-lt-bg px-2 py-1 text-sm text-lt-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-lt-ring"
        >
            {servers.map((server) => (
                <option key={server.url} value={server.url}>
                    {serverLabel(server)}
                </option>
            ))}
        </select>
    );
```

Replace with:

```tsx
    return (
        <NativeSelect
            value={selectedServerUrl ?? ""}
            onChange={(event) => onServerChange(event.target.value)}
            aria-label="Select server"
            className="w-full"
        >
            {servers.map((server) => (
                <option key={server.url} value={server.url}>
                    {serverLabel(server)}
                </option>
            ))}
        </NativeSelect>
    );
```

- [ ] **Step 3: Replace the filter `<input>`**

Find:

```tsx
                <input
                    type="text"
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder="Filter operations…"
                    aria-label="Filter operations"
                    className="w-full rounded-lt-sm border border-lt-input bg-lt-bg px-2 py-1 text-sm text-lt-fg placeholder:text-lt-muted-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-lt-ring"
                />
```

Replace with:

```tsx
                <Input
                    type="text"
                    value={filter}
                    onChange={(event) => setFilter(event.target.value)}
                    placeholder="Filter operations…"
                    aria-label="Filter operations"
                    className="w-full"
                />
```

- [ ] **Step 4: Verify**

```bash
npm run typecheck
npm test
```

- [ ] **Step 5: Manual check**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs`. Confirm the filter input and (if the spec has more than one server) the server picker render with Lattice's standard control chrome (matching the visual weight of other Lattice form controls, e.g. the response status pills) and still function — typing in the filter narrows the operation list, changing the server picker updates the operation URL shown in the header. Stop the server when done.

- [ ] **Step 6: Commit**

```bash
git add resources/js/api-reference/ApiReferenceNav.tsx
git commit -m "feat: use Lattice Input and NativeSelect in the nav sidebar"
```

---

### Task 9: Wire up the SVG icon sprite

**Added after Task 9's original verification pass found a real gap:** DOM inspection (`document.querySelectorAll('symbol')` → `[]`) proved Task 6's copy-button icon and Task 7's chevron icon are structurally present (`<use href="#copy">`, `<use href="#chevron-down">`) but invisible — nothing in the document defines those symbol ids. `workbench/resources/js/app.tsx`'s `<Provider>` never receives a `sprite` prop (defaults to `{ href: "" }`, per `node_modules/@lattice-php/lattice/dist/provider-base.js:10-11`), and no Vite config registers a sprite plugin. Functionality (click-to-copy, expand/collapse) still works — only the glyphs are missing. Human-approved fix: wire up the sprite for the workbench, and document the same requirement for consumers, parallel to Task 1's npm-package documentation.

**Files:**
- Modify: `package.json` (root)
- Modify: `vite.config.ts`
- Modify: `tsconfig.json`
- Modify: `workbench/resources/js/app.tsx`
- Modify: `README.md`

**Interfaces:**
- Consumes: `@lattice-php/vite-svg-sprite`'s `svgSprite` Vite plugin and its `virtual:svg-sprite` module (default export shape `{ href: string, ids?: readonly string[], source?: string }`, matching `SpriteValue` in `node_modules/@lattice-php/lattice/dist/icons/sprite.d.ts`).
- Produces: no new exports — `workbench/resources/js/app.tsx`'s `<Provider>` gains a `sprite` prop.

- [ ] **Step 1: Add the sprite plugin dependency**

```bash
npm install -D @lattice-php/vite-svg-sprite
```

Add it to `package.json`'s `devDependencies` (alphabetical, matching the file's existing `sort-packages`-style ordering) if the install doesn't already place it correctly — verify after running the command.

- [ ] **Step 2: Register the plugin in `vite.config.ts`**

Read the current file:

```bash
cat vite.config.ts
```

It currently reads:

```ts
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["workbench/resources/css/app.css", "workbench/resources/js/app.tsx"],
            publicDirectory: "vendor/orchestra/testbench-core/laravel/public",
            buildDirectory: "build",
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        dedupe: ["react", "react-dom", "@inertiajs/react", "@lattice-php/lattice"],
    },
});
```

Add the `svgSprite` plugin, pointed at Lattice's own bundled icon set (`node_modules/@lattice-php/lattice/resources/icons`, confirmed to contain `chevron-down.svg`, `copy.svg`, `check.svg`, and 54 others — this is the same directory Lattice's own built-in components' icon names resolve against):

```ts
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";
import { svgSprite } from "@lattice-php/vite-svg-sprite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["workbench/resources/css/app.css", "workbench/resources/js/app.tsx"],
            publicDirectory: "vendor/orchestra/testbench-core/laravel/public",
            buildDirectory: "build",
            refresh: true,
        }),
        react(),
        tailwindcss(),
        svgSprite({
            iconDirs: ["node_modules/@lattice-php/lattice/resources/icons"],
        }),
    ],
    resolve: {
        dedupe: ["react", "react-dom", "@inertiajs/react", "@lattice-php/lattice"],
    },
});
```

- [ ] **Step 3: Add the virtual-module client types**

Read `tsconfig.json`. Add `"@lattice-php/vite-svg-sprite/client"` to `compilerOptions.types` (create the `types` array if it doesn't exist; if it exists with other entries, append to it — don't replace).

- [ ] **Step 4: Wire the sprite into the workbench's `<Provider>`**

Read `workbench/resources/js/app.tsx`. It currently reads:

```tsx
import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import {
    createLayoutResolver,
    createPageResolver,
    extendRegistry,
    Provider,
    registry,
} from "@lattice-php/lattice";
import { createRoot } from "react-dom/client";
import spectacularPlugin from "../../../resources/js/plugin";

const appRegistry = extendRegistry(registry, spectacularPlugin);

createInertiaApp({
    resolve: createPageResolver({}),
    layout: createLayoutResolver(),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }
        createRoot(el).render(
            <Provider registry={appRegistry}>
                <App {...props} />
            </Provider>,
        );
    },
});
```

Add the sprite import and pass it to `<Provider>`:

```tsx
import "../css/app.css";
import { createInertiaApp } from "@inertiajs/react";
import {
    createLayoutResolver,
    createPageResolver,
    extendRegistry,
    Provider,
    registry,
} from "@lattice-php/lattice";
import { createRoot } from "react-dom/client";
import sprite from "virtual:svg-sprite";
import spectacularPlugin from "../../../resources/js/plugin";

const appRegistry = extendRegistry(registry, spectacularPlugin);

createInertiaApp({
    resolve: createPageResolver({}),
    layout: createLayoutResolver(),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }
        createRoot(el).render(
            <Provider registry={appRegistry} sprite={sprite}>
                <App {...props} />
            </Provider>,
        );
    },
});
```

- [ ] **Step 5: Document the sprite requirement for consumers**

In `README.md`'s "Displaying docs with Lattice" section, immediately after the existing npm-install paragraph (the one Task 1 updated to include `buffer`), add:

```markdown
If your app doesn't already render Lattice icons elsewhere, you'll also need an SVG sprite for the viewer's copy
button and expand/collapse chevrons to actually be visible (the components render without one, just with empty
icons — nothing errors or warns):

​```bash
npm install -D @lattice-php/vite-svg-sprite
​```

​```ts
// vite.config.ts
import { svgSprite } from "@lattice-php/vite-svg-sprite";

export default defineConfig({
    plugins: [
        // ...your other plugins
        svgSprite({ iconDirs: ["node_modules/@lattice-php/lattice/resources/icons"] }),
    ],
});
​```

Then pass the sprite to your `<Provider>`:

​```tsx
import sprite from "virtual:svg-sprite";

<Provider registry={registry} sprite={sprite}>
```

See [`@lattice-php/vite-svg-sprite`](https://www.npmjs.com/package/@lattice-php/vite-svg-sprite) for merging in your
own icons alongside Lattice's.
```

(The `​` characters above are zero-width markers separating the nested code fences from this instruction's own — when inserting into `README.md`, use real triple-backtick fences, not nested ones; write this as plain content in the file, the same way Task 1's README insertion was written directly rather than through a nested fence.)

- [ ] **Step 6: Verify**

```bash
npm run typecheck
npm test
npm run build
```

All three must succeed. `npm run build` succeeding confirms the `virtual:svg-sprite` import resolves.

- [ ] **Step 7: Manual regression check — this time with DOM inspection, not just a screenshot**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs` in a fresh tab (not a previously-open one, to rule out stale bundle/HMR state) and run in the page's console (or via a JS-execution browser tool):

```js
document.querySelectorAll('symbol').length
```

Expected: greater than 0 (the sprite's symbols are now present in the document). Then select an operation with a deprecated response or expand a schema tree row, and confirm the chevron/copy icons are now visually a real glyph, not a blank 12×12 box — take a screenshot and visually confirm, don't rely on DOM presence of `<use>` alone (that was true before this fix too, and was not sufficient evidence). Stop the server when done.

- [ ] **Step 8: Commit**

```bash
git add package.json vite.config.ts tsconfig.json workbench/resources/js/app.tsx README.md
git commit -m "fix: wire up the Lattice SVG sprite so viewer icons actually render"
```

---

### Task 10: Full verification pass

**Files:** none (verification only).

- [ ] **Step 1: Full automated check**

```bash
npm run typecheck
npm test
npm run build
composer check
```

All four must pass. `composer check` covers Pint/PHPStan/Pest on the PHP side, which this plan didn't touch but should still be green.

- [ ] **Step 2: Full manual regression walkthrough**

```bash
composer serve
```

Open `http://127.0.0.1:8000/docs` and, with the browser devtools console open:

1. Confirm zero console errors on load.
2. Select `GET /categories`. Confirm the response body renders resolved schema fields (the original bug — this must not regress).
3. Confirm the response status selector and (if present) the Schema/Example toggle render as Lattice segmented pills, not plain buttons.
4. Confirm the HTTP method badges (nav list and operation header) render as colored pills matching the verb.
5. Confirm the operation URL has a working copy button.
6. Expand a schema tree row, confirm the chevron icon renders and rotates.
7. Confirm the nav filter input and server picker (if multiple servers) render with Lattice's standard control styling and still work.

Stop the server when done.

- [ ] **Step 3: Push and open a PR**

```bash
git push -u origin feat/api-reference-lattice-polish
gh pr create --title "fix: repair API reference ref resolution, adopt Lattice UI primitives" --body "$(cat <<'EOF'
## Summary
- Ship the Buffer polyfill from the actual plugin entry point (was workbench-only), and document the npm packages a consumer needs alongside @lattice-php/lattice.
- Parse and render response headers from the OpenAPI document.
- Replace hand-rolled tab/pill/input/select/caret UI in the API reference viewer with the equivalent @lattice-php/lattice/ui and /icons primitives (SegmentedPills, Badge, CopyableText, Icon, Input, NativeSelect).

See docs/superpowers/specs/2026-08-02-api-reference-lattice-polish-design.md for the full design rationale, including why the Buffer fix isn't fully self-contained for consumers.

## Test plan
- [x] npm run typecheck / npm test / npm run build
- [x] composer check
- [x] Manual walkthrough of /docs in the workbench: response bodies resolve, tabs/badges/copy button/chevron/inputs all render via real Lattice components, zero console errors
EOF
)"
```
