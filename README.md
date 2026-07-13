# coqui-toolkit-backstory

Optional [Coqui](https://github.com/carmelosantana/coqui) toolkit that generates a
profile's `backstory.md` from the source files under `profiles/<name>/backstory/`. It
is the **producer** of backstory content; Coqui core is the **consumer** that reads the
generated `backstory.md` into the composed prompt. This package bundles every
extractor Coqui's backstory generator previously shipped in core, plus the Word, PDF,
and HTML formats that used to live in the separate `coqui-toolkit-backstory-formats`
mod — all in one toolkit, with no discovery seam to configure.

## Install

```bash
/mods install coquibot/coqui-toolkit-backstory
```

The toolkit self-registers the `/backstory` REPL command via Coqui's toolkit
extensibility mechanism (`ReplCommandProvider`). There is no core wiring to configure —
installing the mod is enough.

## The `/backstory` command

```bash
/backstory              # Show backstory generation status and file summary
/backstory generate     # Assemble backstory.md from the profile's backstory/ source files
/backstory failed       # List failed extractions and unsupported files from the last generation
```

- **`/backstory`** (no argument) — shows the manifest status, file/token counts, and a
  per-folder and per-file breakdown for the active profile. If sources have changed
  since the last generation, it prints a staleness hint: *"Backstory sources changed —
  run /backstory generate to update backstory.md."*
- **`/backstory generate`** — assembles `backstory.md` from
  `profiles/<active>/backstory/`, reports the file count, token estimate, and
  generation time, and confirms the write.
- **`/backstory failed`** — lists files that failed extraction or were skipped as
  unsupported in the last generation.

All three require an active profile (`/profile <name>`); the handler errors clearly
when none is active or the named profile doesn't exist.

## Source layout and output contract

```text
profiles/caelum/
├── soul.md
├── backstory.md               ← generated output (consumed by core)
├── .backstory-manifest.json   ← change-detection manifest
└── backstory/                 ← source files (read by this toolkit)
    ├── 01-origin.md
    ├── 02-milestones.csv
    ├── 03-values.yaml
    ├── personality/
    │   ├── 01-traits.txt
    │   └── 02-quirks.json
    └── timeline.md
```

This toolkit reads sources from `{workspace}/profiles/<active>/backstory/` and writes
`{workspace}/profiles/<active>/backstory.md` plus
`{workspace}/profiles/<active>/.backstory-manifest.json`. Core reads only
`backstory.md` at that path and has no knowledge of how it was produced — the
producer/consumer split is deliberate so core stays generator-free.

## Supported formats

| Extension | Treatment | Dependency |
| --- | --- | --- |
| `.txt` | Plain text, encoding-normalized to UTF-8 | none |
| `.md`, `.mdx` | Passed through as-is after text normalization | none |
| `.json` | Wrapped in a fenced `json` code block (validates JSON) | none |
| `.yaml`, `.yml` | Wrapped in a fenced `yaml` code block | none |
| `.csv`, `.tsv` | Converted to a markdown table | none |
| `.sql` | Simple `CREATE TABLE` / `INSERT ... VALUES` statements become markdown tables; otherwise preserved as fenced `sql` | none |
| `.xml` | Rendered as a markdown outline for simple documents, otherwise fenced `xml` | none |
| `.rtf` | Converted to plain text via conservative RTF control-word stripping | none |
| Code files (`.php`, `.js`, `.ts`, `.jsx`, `.tsx`, `.py`, `.rb`, `.java`, `.c`, `.cpp`, `.cs`, `.go`, `.rs`, `.sh`, `.zsh`, `.ps1`, `.css`, `.scss`, `.less`, and similar) | Wrapped in fenced code blocks with language hints; never executed | none |
| `.xlsx`, `.xlsm` | Markdown table per worksheet, when runtime-supported | `ext-zip` |
| `.pptx`, `.pptm` | Markdown section per slide, with speaker notes appended, when runtime-supported | `ext-zip` |
| `.odt` | Markdown with paragraph/heading/list structure preserved, when runtime-supported | `ext-zip` |
| `.ods` | Markdown table per sheet, when runtime-supported | `ext-zip` |
| `.odp` | Markdown section per slide, when runtime-supported | `ext-zip` |
| `.docx`, `.docm` | Text extracted and converted to markdown | `phpoffice/phpword` |
| `.pdf` | Text extracted | `smalot/pdfparser` |
| `.html`, `.htm` | Sanitized and converted to markdown | `league/html-to-markdown` |

The dependency-free set, `.docx`/`.docm`, `.pdf`, and `.html`/`.htm` are all hard
requires of this package's `composer.json` — no separate mod is needed for Word, PDF,
or HTML anymore. The `ext-zip`-backed office formats (`.xlsx`, `.pptx`, `.odt`, `.ods`,
`.odp`) degrade gracefully: when `ext-zip` isn't available, those extensions are simply
left out of the extractor map and files with those extensions show up as unsupported in
`/backstory failed`.

None of these extractors execute scripts, formulas, macros, or embedded/active content
— office and markup formats are read-only input, converted to markdown or fenced text.

## Sort order and change detection

Source files are sorted using a **numbered-first natural sort**:

1. Files with numeric prefixes (e.g., `01-intro.md`) sort first, in natural order.
2. Unnumbered files follow alphabetically.
3. Files at each directory level appear before subdirectory contents.
4. Hidden files and directories (starting with `.`) are skipped.

A `.backstory-manifest.json` file tracks SHA-256 hashes of every discovered source
file, including unsupported ones. Generation is skipped when the content hash matches
what was last generated, so repeated `/backstory generate` calls are cheap even with
hundreds of source files, while still detecting newly added unsupported inputs.

## On-demand generation (no auto-regeneration)

Generation only happens when you run `/backstory generate`. There is no boot-time
auto-regeneration — Coqui core no longer regenerates `backstory.md` on startup. The
`/backstory` overview compares the current source tree against the manifest and prints
a staleness hint when they've drifted, so you know to run `/backstory generate`
yourself.

## Budget caveat

`backstory.md` is loaded and pinned into the system prompt on every turn once a profile
is active — it is not paged in on demand. Keep source content proportionate to what
the profile actually needs; large or redundant backstory sources inflate every request
in that profile's context budget.

## API routes

This toolkit does not provide any HTTP API routes. `/backstory` is a REPL-only
command; API exposure is out of scope for this package.

## Development

```bash
composer install
composer test
composer analyse
```
