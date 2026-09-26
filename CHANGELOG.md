# Changelog

All notable changes to `laranail/artisan-ui` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-09-26

First release. It adopts [`lorisleiva/artisan-ui`](https://github.com/lorisleiva/artisan-ui) (last commit 2021-06, Laravel 8 only) into the laranail family on Laravel 13 and PHP 8.4/8.5. It folds in every upstream issue and pull request, and the features of [`pabloleone/artisan-ui`](https://github.com/pabloleone/artisan-ui) and [`dev-arindam-roy/artisan-ui`](https://github.com/dev-arindam-roy/artisan-ui), on an authorization model none of the three had.

### Upstream issues and pull requests

- lorisleiva #2: the arguments panel still opens when a command has required arguments, and no longer renders invalid JavaScript (`{ open:  }`) when it does not.
- lorisleiva #3: the open command group is kept in the URL hash.
- lorisleiva #6, #7, #10: superseded by the Laravel 13 upgrade. The output panel scrolls horizontally (#7), and every run is non-interactive (#10), so a prompting command answers its default instead of hanging a worker.
- lorisleiva #9: allow and deny lists, with the matching fixed so `migrate:*` also covers `migrate`.
- pabloleone `pabloleone-patch-1`: the unmerged before/after events, as `CommandExecuting`, `CommandExecuted` and `CommandFailed`.
- pabloleone #2: rerunning a command with the same input is covered by a regression test.

### Access

- The panel is off by default, allowed only in `local`, and every Gate ability denies until the application defines it. An application definition always wins over the package default.
- Access is decided for the signed-in user of a configurable guard, through four abilities: `laranail-artisan-ui.access`, `.run` (given the command and its input), `.view-history` and `.view-environment`. There is no package login and no stored credential.
- Optional IP/CIDR allowlist; every malformed entry fails closed.
- Denials are logged, dispatched as `AccessDenied`, and never explain themselves to the requester. Browsers get the panel's own denial page, which stays within the CSP; JSON clients get the bare status.

### Running commands

- Discovery of every Laravel command, grouped by namespace, with search. Hidden and console-level commands are left out.
- Risk classes decide what a run takes: safe; writes files (only in configured environments); destructive (the command name typed back and a fresh password confirmation); forbidden (never listed or runnable: long-running processes, interactive shells, and `config:show`).
- One POST endpoint runs everything: CSRF, a per-user rate limit, validation against the command's own definition (global options such as `--env` refused), per-command locks, bounded input and output.
- Output keeps its ANSI colour as text segments, rendered with `textContent`; secrets are redacted before anything is shown or recorded.
- Quick actions (caches, storage, database, framework tables with a duplicate-migration guard, generators, maintenance) pre-fill a form and never run on their own.
- Output decorators per command pattern, from configuration or `ArtisanUI::decorate()`.
- An environment panel, gated by its own ability.

### Audit

- Every run is recorded when it starts and when it ends: to a log channel by default, or to `laranail_artisan_ui_runs` with the database driver, which adds a filterable History screen and rerun.
- The run table is pruned daily on the scheduler after `audit.retention_days`, while `audit.schedule_prune` is on (the default).
- If the table disappears mid-run (`migrate:fresh`), the record falls back to the log and the audit store is reported as degraded.

### Hardening from review

- Text a command prints with `echo` or `print` is captured into the run output, and so capped, redacted and audited, instead of leaking into the HTTP response.
- Redaction:
  - runs in linear time (it was quadratic on long runs of blank lines);
  - also scrubs secrets that exist only in configuration, as under `config:cache`;
  - masks the head of a secret cut in half at the output cap.
- Risk classes:
  - `invoke-serialized-closure` and `schedule:finish` are forbidden even when hidden commands are listed;
  - `schedule:test`, `queue:retry`, `queue:retry-batch` and `env:encrypt` are destructive.
- The exception reported for a failed run carries a redacted copy of its cause chain (class, location and scrubbed trace kept), so a DSN in a driver's message never reaches logs or monitoring.
- A run killed by the time limit or a fatal error is recorded as errored and its lock released, from a shutdown handler; lock lifetimes are clamped to the time limit plus a minute.
- A guest from a disallowed environment or address gets a 403, not a login redirect that reveals the panel.
- The client:
  - sends one run however fast Run or the password dialog is submitted;
  - never leaves "Running…" behind;
  - falls back when `<dialog>.showModal()` is missing;
  - supports value-optional options ("send without a value") and negatable flags (`--no-name`).
- Accessibility:
  - errors inside collapsed sections are revealed;
  - every list row is labelled;
  - removing a row keeps focus;
  - the Rerun link is no longer nested in a disclosure button;
  - dark-mode muted text meets WCAG AA contrast.
- Dark mode follows the system preference in CSS, so pages never flash light and the script-free denial page is dark too.
- Echo capture keeps its order relative to console output and honours `ob_clean()`. A command that removes the capture buffer gets a note and a logged warning.
- Config-seeded redaction is restricted to credential keys, so stock config such as the `_cache` store key and facade aliases no longer masks ordinary output.
- Lock lifetimes are never capped below what the operator configured; a run whose listener throws is closed out at once rather than at worker exit; the shutdown guard releases its state.
- Cancelling the password dialog while the check is pending cancels the run.
- A disabled panel answers the framework's bare 404.
- History rerun restores negated flags and valueless options.
- Every user-facing string, validator messages included, is in the translation file. A test fails on any key the code asks for that the file lacks.

### Failure handling

- A command that throws is recorded as errored and reported to the exception handler with its cause preserved; the browser gets a run id, in every environment.
- A failing output decorator withholds the output instead of showing it undecorated.
- Tolerated anomalies (truncated output, a cache store that cannot lock, an audit fallback) are logged at warning level.

### Tooling

- `laranail::artisan-ui.install`, `laranail::artisan-ui.doctor` (also part of `laranail::package-tools.doctor`) and `laranail::artisan-ui.policy`. The doctor probes the cache store, since an unreachable one fails every run in the rate limiter before the panel is consulted.
- A workbench application (`composer serve`) with an admin and a non-admin user, for trying the panel end to end.
- `ArtisanUI::fake()` for applications' own tests.
- A framework-free ES module client with a composition root, cancelable `laranail-artisan-ui:*` events and hooks, and a strict CSP. The client (9 KB) and the compiled Tailwind stylesheet (31 KB) are committed and served from a content-hashed route; upstream shipped an unpurged 4 MB stylesheet and loaded Alpine and axios from a CDN without version pins.
