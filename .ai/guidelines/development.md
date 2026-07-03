# Local Development

- This package is developed with Orchestra Testbench, not a full Laravel app.
- `artisan` at the repo root is a symlink to `vendor/bin/testbench`, so `php artisan <command>` boots the Testbench
  skeleton with this package's service provider and the `workbench/` app.
- Run the test suite with `composer test` or `./vendor/bin/pest`.
- Serve the workbench app with `composer serve`.
- The two Artisan commands the package ships are `spectacular:openapi` and `spectacular:asyncapi`; run them against the
  workbench app to see generation end to end.
- The AI tooling overrides for Boost live in `workbench/app/Support/` and are wired in
  `Workbench\App\Providers\WorkbenchServiceProvider`. They point Boost at the package root instead of the Testbench
  skeleton.
- Regenerate `CLAUDE.md` and `AGENTS.md` after editing files in `.ai/guidelines/` with `php artisan boost:update`
  (or `composer boost:refresh`).

## Verification

- Before pushing or opening a PR, run `composer check` (Pint, PHPStan, Pest) — it mirrors CI. Never push on red.
  `composer test` alone is not enough; PHPStan (`composer analyse`) and Pint (`composer test:lint`) run in CI too.
- Generation output is verified against committed fixtures in `workbench/fixtures/` (`openapi.json`, `asyncapi.json`)
  and `tests/Fixtures/`. When a change intentionally alters generated output, regenerate and commit the updated fixture
  in the same change; when it does not, a fixture diff is a regression to investigate, not to overwrite.

## Comments

- Code must be self-explanatory: reach for clear names, small functions, and types before a comment.
- Do not add comments. A comment is a last resort and explains only *why* something is done, never *what* the code does.
- When you encounter an obsolete, redundant, or "what" comment, delete it.
- Delete section banners and navigation comments unless they explain a non-obvious boundary.
- Delete comments that narrate the next line, assertion, or obvious test setup; prefer clearer test names and variable names.
- Keep PHPDoc only when it carries type information, public API intent, static-analysis value, generated-file context,
  or a non-obvious constraint.
- Keep comments that explain framework quirks, ordering requirements, cache/build behavior, performance traps, or other
  constraints that are hard to infer from the code alone.

## Testing

- Prefer feature tests for backend behavior. Exercise the package through its Artisan commands, the Scramble
  OpenAPI extensions, and the AsyncAPI generator, asserting on the generated documents and database/side effects rather
  than isolating internals by default.
- Use unit tests only for complex algorithms implemented as pure functions or small deterministic value objects (e.g.
  schema factories, class discovery) where integration coverage would make the important cases hard to see.
- Assert generated specs against fixtures where a full document is the meaningful contract; assert on targeted fragments
  where a single behavior is under test.
