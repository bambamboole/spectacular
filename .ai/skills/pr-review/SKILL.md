---
name: pr-review
description: Use when reviewing a Spectacular pull request, branch diff, or set of staged/working changes for quality — reuse, simplification, efficiency, and altitude cleanups, plus adherence to the project guidelines. Review-only it surfaces findings and never commits the changes it proposes.
---

# Spectacular PR Review

Review changed code for **quality**, applying the `/simplify` discipline so any agent can run it
even without the built-in skill. The mandate here is: is the change as simple, reused, efficient, and
well-placed as it should be, and does it follow the project guidelines?

This is **not** a broad bug hunt — for a full correctness sweep (logic errors, races, security, all
edge cases) use `/code-review`; the two are complementary and a thorough PR pass runs both. But
"not a bug hunt" is **not** a licence to ignore correctness you stumble into. When a hunk you are
already reading raises a *concrete* correctness doubt, **trace it to ground before deciding** —
follow the data path, read the handler, confirm whether the defect is real. Then either report a
verified defect as a finding, or raise it as an explicit question if the behavior is genuinely
ambiguous. **Never defer an un-investigated suspicion** with "run /code-review" — only a broad
sweep gets deferred, never the specific doubt in front of you.

**Core principle:** every finding must be behavior-preserving and earn its place. Suggest the
change a senior engineer would actually make — not a restyle, not a nitpick a linter already catches.

## Operating Rules (non-negotiable)

- **Never review on the main checkout unless the user explicitly asks for an in-place review.** Run
  the review in a dedicated worktree (see the `worktrees` skill). The main checkout may hold another
  agent's in-progress work, and the path-verification edits below would entangle with it. For a
  GitHub PR, check out its branch in a fresh sibling worktree; for a local branch, add a worktree for
  it. Only stay on the main checkout when the user explicitly says to review it there.
- **Review-only. Never commit, push, or leave the proposed change as the deliverable.** Your output
  is findings, not edits to merge.
- You **may** edit code locally to *verify a path* — confirm a refactor type-checks, that a test
  still passes, that an existing helper really covers the case. After verifying, revert it (or leave
  it clearly out of any commit). The deliverable is the finding, never the scratch edit.
- **Scope to the diff.** Only review lines the PR added or touched, plus the immediate context
  needed to judge them. Do not flag pre-existing issues on unmodified lines.
- **Preserve behavior.** If a suggestion changes outputs, side effects, or edge-case handling, it is
  out of scope — note it as a question, don't present it as a cleanup. Generated OpenAPI/AsyncAPI
  output is a contract: a change to it is a behavior change, never a "cleanup".
- **Trace concrete correctness doubts to ground.** If a touched hunk makes you suspect a real defect
  (a value that can reach a branch that silently drops it, an unhandled schema shape, a discovered
  class that no longer resolves), follow it through the code until you can confirm or refute it —
  read the handler, trace the data flow, check the type at every hop. Report a confirmed defect as a
  finding (or a genuinely ambiguous one as a question). Deferring an *unverified* correctness
  suspicion to `/code-review` is not allowed; only a broad, out-of-scope sweep is deferred.
- **Confidence gate.** Only surface findings you are confident improve the code. When unsure, drop
  it. A short list of real improvements beats a long list of maybes.

## The Four Simplify Dimensions

These are the lenses to read every changed hunk through. Knowledge extracted from `/simplify` so it
survives without the skill loaded.

### 1. Reuse — *is this reinventing something we already have?*

The change reimplements logic, a helper, or a component the codebase (or framework) already provides.

Look for:
- Duplicated/copy-pasted blocks that should be one function.
- A hand-rolled helper mirroring an existing util, trait, base class, Collection method, or Laravel
  helper (`str()`, `collect()`, `data_get()`, `Arr::`, `Str::`).
- Re-deriving a value already available on the request, config, or a value object.
- Reimplementing schema/type inference that Scramble (or the package's own `PayloadSchemaFactory` /
  extensions) already performs.

Fix: call the existing thing. Before claiming "this already exists", grep for it and confirm.

```php
// ❌ reimplements Collection::pluck + keyBy
$result = [];
foreach ($messages as $message) {
    $result[$message->name] = $message->payload;
}

// ✅ reuse
$result = collect($messages)->pluck('payload', 'name')->all();
```

### 2. Simplification — *is this more complex than the job needs?*

Look for: dead/unreachable code, redundant conditionals, one-caller indirection, needless nesting,
parameters/flags that are always the same value, intermediate variables that don't aid clarity,
nested ternaries, defensive checks that cannot trigger.

Fix: remove the complexity, keep the behavior. **Balance:** explicit beats clever. Do not compress
into dense one-liners or nested ternaries to save lines — prefer early returns and guard clauses.

```php
// ❌ nested ternary, hard to scan
$type = $nullable ? ($array ? 'array' : 'object') : 'string';

// ✅ readable
if (! $nullable) {
    return 'string';
}

return $array ? 'array' : 'object';
```

### 3. Efficiency — *does this do needless work?*

Look for: N+1 queries, DB/HTTP/reflection calls inside loops, recomputation inside loops, scanning
the same paths or reflecting the same classes more than once, loading more than is used, work done
eagerly that is rarely needed.

Fix: hoist out of the loop, batch, memoize class discovery / reflection where it counts, select only
what is needed. Do **not** micro-optimize cold paths at the cost of readability.

```php
// ❌ re-discovers classes on every message
foreach ($events as $event) {
    $classes = $this->discoverer->discover($paths);   // scans the filesystem each iteration
    // ...
}

// ✅ discover once, reuse
$classes = $this->discoverer->discover($paths);
foreach ($events as $event) {
    // ...
}
```

### 4. Altitude — *is the code at the right level of abstraction?*

Code sits at the wrong layer relative to its surroundings, or mixes layers within one function.

Look for: low-level details leaking into a high-level function; a single function mixing concerns
from different layers (Artisan command + reflection + document assembly + file I/O); a wrapper that
adds an indirection layer with no value; logic in the wrong place (a console command doing schema
inference a factory should own, an extension reaching into filesystem discovery).

Fix: move logic to the layer it belongs to, so each function reads at one consistent altitude.

```php
// ❌ command drops to reflection + schema-building altitude
public function handle(): int
{
    foreach ((new ClassDiscoverer)->discover($paths) as $class) {
        $ref = new ReflectionClass($class);
        // ...builds the whole schema inline...
    }
}

// ✅ command stays high; generator + factory own the lower altitudes
public function handle(AsyncApiGenerator $generator): int
{
    file_put_contents($this->output, $generator->generate()->toJson());

    return self::SUCCESS;
}
```

## Guideline Adherence (highest-signal checks)

The full rules live in `CLAUDE.md` / `.ai/guidelines/`. During review, weight these — they are the
ones most often violated and explicitly mandated:

- **Comments:** no "what" comments. Flag any comment restating the code. Comments may only explain
  *why*. Obsolete/redundant comments in touched code should be deleted.
- **PHP:** constructor property promotion; explicit return types and parameter type hints; curly
  braces on all control structures even single-line; `TitleCase` enum cases; array-shape PHPDoc; no
  empty zero-arg constructors; `declare(strict_types=1)`.
- **Testing:** feature tests exercising the Artisan commands, Scramble extensions, and AsyncAPI
  generator against generated documents and side effects; unit tests only for pure
  algorithms/value objects (schema factories, class discovery). Flag a unit test that should be a
  feature test.
- **Generated output is a contract:** when a change alters `workbench/fixtures/*.json` or
  `tests/Fixtures/`, the regenerated fixture must be committed in the same change and the diff must
  be intentional. Flag a source change that would drift the fixtures without regenerating them.
- **Git:** one logical change per commit with a conventional-commit subject; no agent attribution /
  `Co-Authored-By`; concise PR description.

## What NOT to Flag (false-positive suppression)

- Anything a linter, formatter, type-checker, or compiler catches (imports, types, spacing,
  newlines) — CI runs `composer check` (Pint, PHPStan, Pest) separately.
- Pre-existing issues on lines the PR didn't touch.
- Pedantic nitpicks a senior engineer would wave through.
- Intentional changes clearly tied to the PR's purpose.
- Issues a guideline flags but the code explicitly silences (e.g. a lint-ignore / PHPStan ignore).
- General "add more tests / docs" wishes unless a guideline requires it for this change.

## Workflow

1. Unless an in-place review was explicitly requested, set up a dedicated worktree for the change
   under review (see the `worktrees` skill) and run the review there — not on the main checkout.
2. Establish the diff. For a branch/working tree: `git diff main...HEAD` (or `git diff` for
   uncommitted). For a GitHub PR: `gh pr diff <n>` and `gh pr view <n>`.
3. Read each changed hunk through the four dimensions, then the guideline checks.
4. For any non-obvious finding, **verify the path**: grep for the existing helper, or apply the
   refactor locally and run the relevant gate (`vendor/bin/pest --filter=...`, `composer check`) to
   confirm it's behavior-preserving — then revert.
5. Apply the confidence gate; drop the maybes.
6. Report findings only. Do not commit.

## Findings Output Format

Group by dimension or by file. For each finding give: location, dimension/rule, why, and a concrete
suggested change. Cite `file_path:line`.

```
### PR Review — <branch or #PR>

Found N findings.

1. [Reuse] src/AsyncApi/Support/PayloadSchemaFactory.php:42
   Reimplements schema inference Scramble already provides. Delegate to the existing type transformer.

2. [Simplification] src/OpenApi/Extensions/QueryBuilderExtension.php:88
   Nested ternary for the parameter type. Use a guard clause + single ternary (behavior unchanged).

3. [Guideline: comments] src/AsyncApi/Support/ClassDiscoverer.php:15
   "// loop over the classes" restates the code. Guidelines forbid "what" comments — delete it.
```

If nothing meets the gate:

```
### PR Review — <branch or #PR>

No findings. Checked reuse, simplification, efficiency, altitude, and guideline adherence.
```

## Common Mistakes

- Presenting scratch edits (used to verify a path) as the change. Revert them; deliver findings.
- Flagging behavior changes as "cleanups". If outputs/edge cases (or generated specs) shift, it's a
  question, not a fix.
- Reviewing the whole file instead of the diff.
- Listing low-confidence nitpicks to look thorough — it dilutes the real findings.
- Recommending an existing helper without grepping to confirm it exists and fits.
- Hiding behind "not a bug hunt" to skip a correctness doubt you already noticed. Trace it to ground;
  defer only a broad sweep, never the specific suspicion in the diff in front of you.
