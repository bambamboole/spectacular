# API reference: fix ref-resolution crash, adopt real Lattice UI primitives

## Context

A consumer (Artisan OS) installed the released package and pointed it at Lattice. The client viewer renders navigation and parameter lists correctly, but:

1. Response/request bodies never resolve — the schema panel shows `ReferenceError: Buffer is not defined` instead of the dereferenced schema.
2. The response-status selector and Schema/Example toggle are hand-styled `<button>` groups that don't match Lattice's actual tab/pill look.
3. Several other elements (method badge, required/deprecated markers, expand carets, nav filter input, server `<select>`) are similarly hand-rolled instead of using Lattice's shipped components.
4. Response headers from the OpenAPI document are parsed nowhere and shown nowhere.

Root cause of (1): `resources/js/schema/build-rows.ts` uses `@apidevtools/json-schema-ref-parser`, which touches Node's `Buffer` global. The workbench app (our dev/demo harness) works around this with a manual polyfill in `workbench/resources/js/polyfills.ts` — but that file is never shipped. `resources/js/plugin.ts` is the actual entry point every consumer's bundle imports, and it has no such polyfill, so `Buffer` is undefined in any real app's bundle.

(2)–(4) exist because `@lattice-php/lattice` ships real, reusable UI building blocks under `@lattice-php/lattice/ui` (confirmed present in `node_modules/@lattice-php/lattice/dist/ui`: `SegmentedPills`, `Badge`, `CopyableText`, `Input`, `NativeSelect`, plus an `Icon`/sprite system under `@lattice-php/lattice/icons`) that the original `OperationView`/`ApiReferenceNav` implementation didn't use — likely written before checking what was available.

## Goals

- Fix the crash so response/request bodies render for the workbench and for any consumer with the required npm packages installed, and document those packages so that's a known, one-line requirement rather than a silent failure.
- Replace every hand-rolled control in the API reference viewer with the equivalent Lattice primitive, so it visually matches the rest of a Lattice-based app.
- Parse and render response headers.

## Non-goals

- No new layout/IA changes (sidebar vs. stacked layout, nav structure) — out of scope.
- No changes to the OpenAPI/AsyncAPI generators themselves.
- No visual redesign beyond swapping hand-rolled elements for existing Lattice components — we are not inventing new visual language.

## Design

### 1. Ship the Buffer polyfill

Move the shim from `workbench/resources/js/polyfills.ts` into `resources/js/plugin.ts`, as a side effect at the top of the module (before the lazy `ApiReference` import is registered):

```ts
import { Buffer } from "buffer";
import { createPlugin, lazyComponent } from "@lattice-php/lattice";

const globalWithBuffer = globalThis as typeof globalThis & { Buffer?: typeof Buffer };
globalWithBuffer.Buffer = globalWithBuffer.Buffer ?? Buffer;

export default createPlugin({ ... });
```

`plugin.ts` is imported eagerly (it's how a consuming app registers the component), so the shim runs before the lazily-loaded `ApiReference` chunk — and everything under it, including `build-rows.ts` — ever evaluates.

**This is not a fully self-contained fix, and that's worth stating plainly.** `bambamboole/spectacular` ships raw TSX source through Composer — Lattice's Vite plugin discovers `resources/js/plugin.ts` directly out of `vendor/`, there is no npm-publish step, and nothing here can install an npm package into a consumer's `node_modules`. `import { Buffer } from "buffer"` only resolves at a consumer's build time if `buffer` is already present in their dependency tree. It is not a transitive dependency of `@apidevtools/json-schema-ref-parser` (confirmed: ref-parser's only dependency is `js-yaml`) or of anything else `resources/js` imports — it's currently just a hand-added `devDependency` of this repo's own `package.json`, there for the workbench's benefit.

So this change turns today's silent runtime crash (`ReferenceError: Buffer is not defined`, feature silently broken) into, for a consumer missing `buffer`, a build-time module-resolution error — louder and clearer, but not a guarantee the feature works out of the box. To close that gap: add a README section listing every npm package `resources/js` requires — `@lattice-php/lattice`, `@apidevtools/json-schema-ref-parser`, `@stoplight/json-schema-tree`, `buffer` — so a consumer wiring up the viewer knows what to `npm install` up front. `buffer` also moves from `devDependencies` to `dependencies` in this repo's own `package.json`, since it's a real runtime requirement of the shipped plugin now, not a workbench-only concern.

Delete `workbench/resources/js/polyfills.ts` and its import in `workbench/resources/js/app.tsx` once the shim lives in `plugin.ts` — keeping both would just be a second place to get out of sync.

### 2. Component-reuse pass

| File | Element | Replace with |
|---|---|---|
| `OperationView.tsx` — `ResponsesSection` | status-code button group | `SegmentedPills` (`options` = one per response contract, `value`/`onSelect` drive `active` index) |
| `OperationView.tsx` — `SchemaExampleView` | Schema/Example button group | `SegmentedPills` |
| `OperationView.tsx` — header method pill | raw `<span className="bg-lt-primary">` | `Badge`, color mapped by verb: `GET`→`info`, `POST`→`success`, `PUT`/`PATCH`→`warning`, `DELETE`→`danger`, else `default` |
| `OperationView.tsx`/`ApiReferenceNav.tsx` — required (`*`) / deprecated markers | raw colored `<span>` | `Badge` (`danger` / `muted`) |
| `OperationView.tsx` — operation URL in header | plain text | `CopyableText` wrapping the full `baseUrl + path` |
| `SchemaView.tsx` — expand/collapse caret | literal `▾`/`▸` glyphs | Lattice `Icon` (chevron, rotated by `open` state) |
| `ApiReferenceNav.tsx` — filter input | raw `<input>` | Lattice `Input` |
| `ApiReferenceNav.tsx` — server picker | raw `<select>` | Lattice `NativeSelect` |

A small `httpMethodColor(method: string): ColorName` helper (colocated in `OperationView.tsx`, the only place that needs it) backs the method badge mapping in both `OperationView` and the nav row, since both currently duplicate the raw method label.

`SegmentedPills` takes `options: Option[]` (`{label, value, data}`), a single `value`, and `onSelect`. Both call sites currently track selection by array index (`useState(0)`); they switch to tracking the selected `Contract`'s derived string key (`contractLabel(contract)` already exists and is unique per contract) so it satisfies `SegmentedPills`' string-`value` contract without introducing a second id scheme.

### 3. Response headers

`Contract` (`types.ts`) gains a `headers: Param[]` field — reusing the existing `Param` type (`{name, location, required, deprecated, description, schema}`) rather than inventing a new shape, since a response header and a request parameter carry the same information. `location` is hardcoded to `"header"` for these.

`parse.ts`'s `buildResponses` reads `resolved.headers` (a `Record<string, RawParameter>` in OpenAPI, values are `$ref`-able the same way operation parameters are) and maps each entry through the existing `buildParam`-style logic into a `Param[]`, attached to every contract for that status code (mirroring how `title`/`examples` are already shared across a status's media types).

`ResponsesSection` renders `current.headers` via the existing `ParamGroupSection`/`ParamRow` components (`OperationView.tsx`) under a "Response headers" heading, above the body — no new list-rendering component needed, just reuse of what parameters already use.

## Data flow

No change to how the spec document reaches the client (inline `spec` prop or `url` + fetch, unchanged). The only new data extracted is `headers` per response contract, sourced from the same already-fetched OpenAPI document.

## Error handling

- The Buffer shim is unconditional (`?? Buffer`) and inert if `Buffer` already exists (e.g., a consumer's own polyfill, or a non-Vite bundler that already provides it). If a consumer lacks the `buffer` npm package, this trades today's silent runtime crash for a build-time "cannot resolve module 'buffer'" error — a regression in strictness but an improvement in diagnosability, mitigated by documenting the requirement (see Design §1).
- `SchemaView`'s existing `error` state (catching `buildSchemaRows` rejections) is unchanged and remains the fallback if dereferencing fails for an unrelated reason (e.g., a genuinely malformed `$ref`).
- Missing/absent `headers` on a response contract is treated as `[]`, same pattern as the existing `examples: []` default — `ParamGroupSection` already no-ops on an empty `params` array via the existing `paramGroups.length > 0` guard pattern, extended to `headers.length > 0`.

## Testing

- `resources/js/api-reference/parse.test.ts`: new cases for `buildResponses` header extraction, including a `$ref`-based header.
- `resources/js/schema/build-rows.test.ts`: unaffected by the polyfill relocation (it doesn't import `plugin.ts`), no new cases needed there.
- `npm run typecheck` and `npm test` after all changes.
- Manual verification (no automated browser test exists for the shipped bundle today, and this is exactly the gap that caused the original bug): rebuild the workbench (`composer serve`), load `/docs`, confirm zero console errors and that a response body actually renders with resolved (not `$ref`-name-only) fields. This is the regression check for the Buffer crash specifically, since it only reproduces once code runs as a real bundled consumer would run it, not inside Vitest.
