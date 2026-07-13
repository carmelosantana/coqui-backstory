# Implementation Plan — coqui-toolkit-backstory

**Date:** 2026-07-13
**Design source of truth:** coqui `docs/superpowers/specs/2026-07-12-identity-backstory-consolidation-design.md` (§3 Phase 3 + output contract). Brainstorm decisions confirmed 2026-07-13.
**Repo:** this package (`/home/carmelo/Projects/CoquiBot/Core/coqui-toolkit-backstory`). A NEW Composer package. **Zero changes to coqui core.**
**Pairing:** must publish BEFORE coqui DRAFT PR #157 (`feat/identity-backstory-consolidation`) flips to ready + merges.

## Objective

Relocate the backstory *generator* out of coqui core into this optional toolkit so PR #157 can drop it from core without users losing `/backstory` + generation. The toolkit is a **producer** (reads `backstory/` sources → writes `backstory.md`); core is the **consumer** (reads `backstory.md`).

---

## Global Constraints (BINDING — copy into every reviewer prompt)

- **Package identity:** name `coquibot/coqui-toolkit-backstory`; PSR-4 root namespace `CoquiBot\Toolkits\Backstory\` (`src/`); extractors under `CoquiBot\Toolkits\Backstory\Extractor\`; tests namespace `CoquiBot\Toolkits\Backstory\Tests\`.
- **Coding standards (coqui):** PHP 8.4, `declare(strict_types=1);` in every file, `final` by default, one class per file, 4-space indent, constructor injection, early returns, explicit exceptions over null. Comments explain *why*.
- **Zero core changes.** coqui core at `/home/carmelo/Projects/CoquiBot/Core/coqui` is READ-ONLY. Port source via `git -C /home/carmelo/Projects/CoquiBot/Core/coqui show main:<path>` (the generator lives on `main`; it is deleted on the #157 branch). Never edit any file under the coqui repo.
- **Heavy deps are hard requires:** `phpoffice/phpword ^1.0`, `smalot/pdfparser ^2.0`, `league/html-to-markdown ^5.1`. Retain each extractor's `isRuntimeSupported()` guard (class_exists / ext checks) for safety, unchanged.
- **No extractor discovery seam.** `ExtractorFactory` instantiates every extractor directly. Do NOT port `BackstoryExtractorDiscovery`. Do NOT declare `extra.php-agents.backstoryExtractors`.
- **`/backstory` self-registers** via `CoquiBot\Coqui\Contract\ReplCommandProvider` on the toolkit class. Never wire into core.
- **API routes OUT of scope** (→ brief #7 `ApiRouteProvider`). Do NOT port `src/Api/Handler/BackstoryHandler.php` or any HTTP route. Do NOT port its test.
- **No boot auto-regen** — core has no boot/lifecycle-hook extension point (verified: only `ReplCommandProvider` exists). Regeneration is on-demand via `/backstory generate`; the no-arg overview prints a staleness hint when `needsRegeneration()` is true. Do NOT port `RunCommand`'s `autoRegenerateBackstory`.
- **Output-path contract (authoritative):** read sources from `{workspace}/profiles/<active>/backstory/`; write `{workspace}/profiles/<active>/backstory.md` and `{workspace}/profiles/<active>/.backstory-manifest.json`. This path MUST match core's read site on the #157 branch (`OrchestratorAgent.php:1113`, `PromptLoader.php:261` → `{activeProfilePath}/backstory.md`).
- **Core coupling severed locally:** a small `src/Support/ProfilePaths.php` replaces `CoquiBot\Coqui\Config\ProfileDiscovery` (`profileExists`/`getProfilePath` == `{workspace}/profiles/{name}` + is_dir) and the `ProfilePreferences::getBackstoryLabel()` read. Do NOT import core `ProfileDiscovery`/`ProfilePreferences`.
- **Contracts:** REPL contracts (`ReplCommandProvider`, `ToolkitCommandHandler`, `ToolkitReplContext`, help/tab-completion) come from a dev/CI stub `stubs/coqui_repl_contracts.php` copied verbatim from `coqui-toolkit-images`; at runtime coqui provides the real ones. The extractor contracts (`ExtractorInterface`/`ExtractorResult`/`BackstoryTextReader`) are now OWNED by this package (real classes in `src/Extractor/`) — no stub for those.
- **Quality gates:** `composer analyse` (PHPStan level 8) green; `composer test` (Pest) green. Both on PHP 8.4.

---

## Task 1 — Package scaffold & build tooling

**Goal:** a buildable, green empty package skeleton.

**Create:**
- `composer.json`:
  - `"name": "coquibot/coqui-toolkit-backstory"`, `"type": "library"`, `"license": "MIT"`, author Carmelo Santana, keywords (coqui, backstory, toolkit, php-agents).
  - `require`: `"php": "^8.4"`, `"phpoffice/phpword": "^1.0"`, `"smalot/pdfparser": "^2.0"`, `"league/html-to-markdown": "^5.1"`.
  - `require-dev`: `"carmelosantana/php-agents": ">=0.14"`, `"pestphp/pest": "^3.0"`, `"phpstan/phpstan": "^2.0"`.
  - `autoload.psr-4`: `{"CoquiBot\\Toolkits\\Backstory\\": "src/"}`.
  - `autoload-dev`: `files: ["stubs/coqui_repl_contracts.php"]`, `psr-4: {"CoquiBot\\Toolkits\\Backstory\\Tests\\": "tests/"}`.
  - `extra.php-agents`: `toolkits: ["CoquiBot\\Toolkits\\Backstory\\BackstoryToolkit"]`, plus a `description`. (No `backstoryExtractors`.)
  - `scripts`: `"test": "pest"`, `"analyse": "phpstan analyse --memory-limit=1G"`.
  - `config`: `sort-packages: true`, `allow-plugins.pestphp/pest-plugin: true`. `minimum-stability: stable`, `prefer-stable: true`.
- `phpstan.neon`: level 8; `paths: [src, tests]`; `scanFiles: [stubs/coqui_repl_contracts.php]`; `tmpDir: .phpstan-cache`.
- `phpunit.xml`: bootstrap `vendor/autoload.php`, cacheDirectory `.phpunit.cache`, one testsuite `Tests` → `tests` dir; `<source><include><directory>src</directory></include></source>`.
- `.gitignore`: `vendor/`, `.env`, `.DS_Store`, `*.cache`, `.phpstan-cache/`, `.phpunit.cache/`, `.phpunit.result.cache`, `composer.lock`.
- `stubs/coqui_repl_contracts.php`: copy VERBATIM from `/home/carmelo/Projects/CoquiBot/Core/coqui-toolkit-images/stubs/coqui_repl_contracts.php`.
- `tests/Pest.php`: `uses()` wiring for the `tests` suite; port helpers from `coqui-toolkit-backstory-formats/tests/Pest.php` (`createTestDocx`, `cleanupTree`); add a `makeTempDir()` helper (unique temp dir under `sys_get_temp_dir()`).
- `tests/Unit/SmokeTest.php`: a single passing test asserting the package autoloads (e.g. `expect(true)->toBeTrue()` plus `class_exists` on a stub contract) so the suite is green before other tasks land.
- `.github/workflows/ci.yml` + `release.yml`: mirror `coqui-toolkit-images` (PHP 8.4; extensions `mbstring, curl, xml, zip`; `composer test` + `composer analyse`).
- `README.md`: skeleton (title + one-paragraph purpose; full content in Task 5).

**Verify (DoD):** `composer install` succeeds; `composer test` green; `composer analyse` green.

---

## Task 2 — Extractor layer (port + absorb -formats, no discovery seam)

**Goal:** all content extractors + factory in this package, contracts owned locally.

**Port** from `git -C <coqui> show main:src/Backstory/Extractor/<F>` (rewrite namespace `CoquiBot\Coqui\Backstory\Extractor` → `CoquiBot\Toolkits\Backstory\Extractor`, and any internal `use` of sibling classes accordingly):
- Contracts: `ExtractorInterface`, `ExtractorResult`, `BackstoryTextReader`.
- Concrete: `TextExtractor`, `MarkdownExtractor`, `JsonExtractor`, `YamlExtractor`, `CsvExtractor`, `XmlExtractor`, `RtfExtractor`, `SqlExtractor`, `CodeBlockExtractor`, `XlsxExtractor`, `PptxExtractor`, `OdtExtractor`, `OdsExtractor`, `OdpExtractor`, `OpenDocumentArchiveReader`.

**Absorb from** `/home/carmelo/Projects/CoquiBot/Core/coqui-toolkit-backstory-formats/src/` (rewrite namespace `CoquiBot\Toolkits\BackstoryFormats` → `CoquiBot\Toolkits\Backstory\Extractor`; make them implement THIS package's `ExtractorInterface`):
- `DocxExtractor`, `PdfExtractor`, `HtmlExtractor`.

**Rewrite `ExtractorFactory`** to instantiate every extractor directly in the constructor — the existing core set PLUS `DocxExtractor`/`PdfExtractor`/`HtmlExtractor`, each behind its `isRuntimeSupported()` guard where the source has one. Remove the `$additionalExtractors`/discovery parameter and the `BackstoryExtractorDiscovery` call entirely. Do NOT port `BackstoryExtractorDiscovery`.

**Tests** (rewrite namespaces; construct `ExtractorFactory` with no args now):
- Port `main:tests/Unit/Backstory/ExtractorTest.php` and `main:tests/Unit/Backstory/SqlExtractorCharacterizationTest.php`.
- Merge the Docx/Pdf/Html cases from `coqui-toolkit-backstory-formats/tests/ExtractorTest.php`.
- New `ExtractorFactoryTest`: asserts the factory maps every expected extension (incl. docx/pdf/html) and returns the right extractor — replaces the deleted discovery/hook tests.
- Do NOT port `ExtractorFactoryHookTest` or `BackstoryExtractorDiscoveryTest` (they test the removed seam).
- Port fixtures `main:tests/Fixtures/Backstory/real-profile-sample/*` → `tests/Fixtures/Backstory/real-profile-sample/`.

**Verify (DoD):** `composer test` green; `composer analyse` green.

---

## Task 3 — Generator core + ProfilePaths

**Goal:** the assembler pipeline and a local profile-path resolver.

**Port** from `main:src/Backstory/<F>` (rewrite ns `CoquiBot\Coqui\Backstory` → `CoquiBot\Toolkits\Backstory`; `use` of `Extractor\*` stays within the new namespace):
- `BackstoryAssembler`, `BackstoryFileDiscovery`, `BackstoryManifest`, `BackstorySourceInventory`, `BackstoryResult`, `BackstoryFileEntry`, `BackstoryUnsupportedFileEntry`.

**Create `src/Support/ProfilePaths.php`** (`final readonly`, constructed with `string $workspacePath`):
- `profilesDir(): string` → `{workspace}/profiles`.
- `profilePath(string $name): string` → `{workspace}/profiles/{name}`.
- `profileExists(string $name): bool` → is_dir of the above.
- `backstoryLabel(string $profilePath): ?string` → read `{profilePath}/preferences.json`, return `labels.backstory` if present (mirror `ProfilePreferences::getBackstoryLabel()`; **display-only — do not over-invest**; tolerate missing/invalid file by returning null).

**Tests** (rewrite ns; where a source test bypassed discovery via `new ExtractorFactory([])`, use the new no-arg `new ExtractorFactory()`):
- Port `BackstoryAssemblerTest`, `BackstoryFileDiscoveryTest`, `BackstoryManifestTest`.
- New `ProfilePathsTest`: MUST include a case with **multiple profiles present** asserting the ACTIVE profile name resolves to exactly `{workspace}/profiles/<active>` (so generation writes to the correct profile dir). Add a light `backstoryLabel` present/absent case.

**Verify (DoD):** `composer test` green; `composer analyse` green.

---

## Task 4 — Inspection, `/backstory` command handler, toolkit registration

**Goal:** the user-facing surface, self-registered.

**Port + rework `BackstoryInspectionService`** (`main:src/Backstory/BackstoryInspectionService.php`, ns rewrite): replace the `CoquiBot\Coqui\Config\ProfileDiscovery` dependency with `Support\ProfilePaths` (same `profileExists`/`profilePath` semantics). Behavior otherwise unchanged.

**Create `src/Command/BackstoryCommandHandler.php`** implementing `CoquiBot\Coqui\Contract\ToolkitCommandHandler` (and `ToolkitCommandHelpProvider` + `ToolkitTabCompletionProvider`). Behavior ported from `main:src/Repl/Handler/BackstoryHandler.php`, adapted:
- `commandName(): 'backstory'`; `subcommands(): ['generate','failed']`; `usage(): '/backstory [generate|failed]'`; `description()`: one line.
- `handle(ToolkitReplContext $context, string $arg): void` — no `RouteResult`; write output via `$context->io`. Resolve workspace/active profile from `$context->workspacePath` / `$context->activeProfile`; error clearly when no active profile.
  - `generate` → run `BackstoryAssembler::generate($profilePath, label)`, report file count + token estimate, confirm `backstory.md` written.
  - `failed` → list unsupported/failed sources via `BackstoryInspectionService`.
  - `''` (overview) → inspect; when `BackstoryAssembler::needsRegeneration($profilePath)` is true, print a `sources changed — run /backstory generate` hint.
  - unknown → point to `/backstory help`.
- Provide a `ToolkitCommandHelp` page (title, summary, subcommands, examples) and static tab-completion for the subcommands.

**Finalize `src/BackstoryToolkit.php`** implementing `ToolkitInterface` + `ReplCommandProvider`:
- `tools(): array` → `[]` (no agent-facing tools; matches core, which exposed none).
- `guidelines(): string` → short block noting `backstory.md` is generated from `profiles/<name>/backstory/` sources via `/backstory generate`.
- `commandHandlers(): array` → `[new BackstoryCommandHandler(...)]`, constructed with the workspace path (mirror the `fromCoquiContext`/`fromEnv` construction pattern used by `coqui-toolkit-images`).

**Tests:**
- `BackstoryInspectionServiceTest`: port coverage; construct with `ProfilePaths`.
- `BackstoryCommandHandlerTest`: `generate` writes `backstory.md` to `{workspace}/profiles/<active>/backstory.md`; overview prints the staleness hint when sources changed; `failed` lists unsupported entries; no-active-profile path errors. Use a fake/using the real stub `ToolkitReplContext` with a temp workspace.

**Verify (DoD):** `composer test` green; `composer analyse` green.

---

## Task 5 — README & package docs

**Goal:** the toolkit ships its own docs; absorbs what leaves core.

**Write `README.md`** covering: purpose (optional generator toolkit); install (`/mods install coquibot/coqui-toolkit-backstory`); the `/backstory` command (overview / generate / failed); supported formats table (core-set formats + absorbed Word/PDF/HTML with their deps); source layout (`profiles/<name>/backstory/` → `backstory.md`); the output contract (producer/consumer split); the budget caveat; the on-demand (no auto-regen) note. Absorb the `coqui-toolkit-backstory-formats/README.md` content and the "Backstory Generator" section from coqui `main:docs/PROFILES.md` (read for reference; do not edit core).

**Verify (DoD):** internal links valid; every command/subcommand named matches the Task 4 handler; format list matches the Task 2 extractors.

---

## Task 6 — Integration validation against the #157 branch (REAL GATE, controller-coordinated)

**Goal:** prove the toolkit restores `/backstory` + generation on a coqui that has already removed the core generator, AND that core actually consumes the output.

**Method (throwaway, no core commits):**
1. Create a git worktree of coqui at `feat/identity-backstory-consolidation` under scratch (isolated; never commit; do not touch the primary working tree).
2. In that worktree, add a Composer **path repository** pointing at this package and `composer require coquibot/coqui-toolkit-backstory` (modifies only the throwaway worktree's composer.json — not committed, not core).
3. Create a workspace profile with `soul.md` + a `backstory/` dir containing a couple of source files.
4. Drive `/backstory generate` (via the REPL or a minimal boot harness) and assert `profiles/<active>/backstory.md` is written at the contract path.
5. **Authoritative assertion:** boot core's prompt composition on the #157 branch and confirm the composed prompt actually contains `profiles/<active>/backstory.md` content (not merely that the file exists) — i.e. `OrchestratorAgent`/`PromptLoader` loaded it.
6. Confirm `/backstory` registered (appears in help / dispatches) on a core with the generator removed.

**Verify (DoD):** steps 4–6 all pass. `pest` + `phpstan` green is necessary but NOT sufficient — this integration gate is the real DoD.

---

## Task 7 — Deprecate the absorbed `-formats` package (post-build bookkeeping)

**Goal:** clean deprecation trail (publish/archive are the user's actions).

- Edit `coqui-toolkit-backstory-formats/composer.json`: add `"abandoned": "coquibot/coqui-toolkit-backstory"`. (This repo is not coqui core — editing it is allowed.)
- Leave a note in this plan's completion summary that the user must: publish `coquibot/coqui-toolkit-backstory` to Packagist, then mark the `-formats` Packagist package abandoned + archive its GitHub repo.

**Verify (DoD):** `-formats` composer.json carries the `abandoned` marker; note surfaced to the user.

---

## Definition of Done (whole plan)

1. `composer install` builds this package.
2. `composer test` (Pest) + `composer analyse` (PHPStan L8) green.
3. Task 6 integration gate passes on the #157 branch: `/backstory generate` writes `backstory.md` at the contract path AND core loads it into the composed prompt; `/backstory` is registered.
4. `-formats` carries the abandoned marker.

Once green and the package is published, coqui PR #157 flips ready + merges.
