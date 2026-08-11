# DocBundle

Generates documentation for a Symfony project. Its primary use today is producing
**agent-friendly Markdown** references — one file per command/entity/workflow, meant to be
read by an AI agent (or a human) without scanning the codebase. It also ships a Panther-based
screenshot command, and an older Sphinx/RST build command (see the note at the end).

## Installation

```bash
composer require survos/doc-bundle
```

## Configuration

All optional. Each section's `include` list names command/workflow namespaces or exact
names to document; an empty list means "document everything".

```yaml
# config/packages/survos_doc.yaml
survos_doc:
    console:
        include: []   # e.g. ['app', 'doctrine:migrations:migrate'] to limit doc:commands
```

## Commands

### `doc:commands`

Writes one Markdown file per console command (arguments, options, synopsis, help text) plus
an index, filtered by `survos_doc.console.include`. Standard Symfony global options
(`--verbose`, `--env`, etc.) are omitted from the output.

```bash
bin/console doc:commands --output-dir=docs/command
```

### `doc:entities`

Writes one Markdown file per Doctrine entity (fields, types, nullability, relations, and
field-bundle metadata — description, group, API/Meilisearch flags — when present), plus an
index with a Mermaid ER diagram of the whole schema.

```bash
bin/console doc:entities --output-dir=docs/entity
```

### `doc:workflows`

Writes one Markdown file per registered Symfony Workflow/state machine: an inline Mermaid
diagram (renders on GitHub, diffs cleanly as text), a co-located high-resolution `.svg`, the
places/transitions tables, and — when app listeners are subscribed to the workflow's events —
their PHP source inline.

```bash
bin/console doc:workflows [name] --output-dir=docs/workflow --svg
```

Requires `symfony/workflow`; renders nothing (but doesn't error) if no workflows are
registered. The `.svg` output additionally requires graphviz's `dot` binary. `survos/state-bundle`
enriches the diagrams with Survos place/transition metadata when installed.

### `doc:screenshot`

Takes a screenshot of a given URL via Symfony Panther (headless Chrome through the local
Symfony proxy) and saves it to disk.

```bash
bin/console doc:screenshot https://example.wip my-screenshot --dir=public/casts/
```

## Sphinx/RST generation — `doc:build` (not currently active)

The bundle also has a `doc:build` command ("Compile .rst.twig files") for rendering `.rst.twig`
templates into Sphinx source, historically used with `rst_h()` (a Twig function this bundle
provides for RST section headers) plus a local Sphinx toolchain:

```bash
sudo apt-get install python3-sphinx
pipx install sphinx_rtd_theme
pipx install sphinx_fontawesome

# templates/docs/index.rst.twig, tutorial.rst.twig, etc.
bin/console doc:build
cd docs && make html
```

**This path needs further testing before relying on it.** Its own test
(`tests/BuildDocsCommandTest.php`) currently fails against the installed Symfony Console
version (`Application::add()` no longer exists), and the test's own success-message assertion
no longer matches the command's actual output string — so it's been out of sync with the rest
of the bundle for a while. Treat `doc:build` as unmaintained until someone re-verifies it end
to end.
