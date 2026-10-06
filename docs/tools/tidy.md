# Tidy

`laranail::artisan-ui.tidy` (`Simtabi\Laranail\ArtisanUI\Commands\TidyCommand`) is a path-confined maintenance command with six actions: `cache`, `logs`, `temp`, `storage`, `db` and `all`.

```bash
php artisan laranail::artisan-ui.tidy [action] [options]
```

## Options

| Option | Description |
|---|---|
| `action` (argument) | `cache`, `logs`, `temp`, `storage`, `db`, or `all` (the default). |
| `--days=` | Only delete files older than this many days. |
| `--size=` | Only delete files larger than this many MB. With `--days` too, a file must be older **and** larger. |
| `--seed` | (`db`) also run `db:seed` after `migrate:fresh`. |
| `--optimize` | (`cache`) also run `optimize:clear`. |
| `--dry-run` | Show what would be removed without deleting anything. |
| `--unfiltered` | (`storage`) sweep user files with no age or size filter. Refused in production. |
| `--force` | Skip confirmation prompts. Required for the `db` action. |

## Actions

| Action | What it does | Roots under `storage_path()` |
|---|---|---|
| `cache` | Flushes the application cache through `cache:clear`; with `--optimize`, also `optimize:clear`. | none |
| `logs` | Deletes log files, filtered by `--days` and `--size` when given. | `logs` |
| `temp` | Deletes temporary files and the file cache's data. | `app/temp`, `app/tmp`, `framework/cache/data` |
| `storage` | Deletes user files, **only when scoped** (see below). | `app/public`, `app/uploads`, `app/exports` |
| `db` | Runs `migrate:fresh`, then `db:seed` with `--seed`. **Never part of `all`.** | none |
| `all` | `cache`, `logs` and `temp`, plus `storage` only when scoped. Never `db`. | as above |

Without `--force`, each action asks first. In a non-interactive run (CI, a pipe, or the panel, which always passes `--no-interaction`) the question answers no and nothing is deleted. `.gitignore` files are never deleted. An unknown action exits `1`.

## The `storage` action needs a scope

`storage/app/public` is the disk behind `storage:link`: user uploads. Unlike `logs` and `temp`, which hold data the application regenerates, an unfiltered sweep there is not housekeeping.

| Invocation | Result |
|---|---|
| `tidy storage --days=30 --force` | Deletes uploads older than 30 days. |
| `tidy storage --size=100 --force` | Deletes uploads over 100 MB. |
| `tidy storage --force` | **Refused.** Every file would match. |
| `tidy storage --dry-run` | **Refused** too: a full "would delete" list reads as a plan the next `--force` carries out. |
| `tidy storage --unfiltered --force` | Deletes everything, outside production only. |
| `tidy storage --unfiltered --force` in production | **Refused**, with no override. |
| `tidy all --force` | Sweeps cache, logs and temp; **skips** `storage` and says so. |
| `tidy all --days=30 --force` | Sweeps everything, `storage` included. |

> In `laranail/toolkit` through v0.1.0, `tidy storage --force` and `tidy all --force` deleted every file in those roots. The containment guard did not catch it and never would have: it answers "can this delete something outside `storage_path()`", and those roots are inside it. `tests/Feature/TidyUserFileGuardTest.php` is the regression for that incident.

## Safety rules

- **Every deletion is confined to `storage_path()`.** Each root is realpath-resolved and proven to sit inside `realpath(storage_path())`; each candidate file is realpath-resolved and re-checked against its root, so a `..` path or a symlink pointing outside storage is skipped, never followed. Root paths are also screened for `..` segments and null bytes.
- **`--dry-run` deletes nothing.** It lists each candidate and reports the space that would be freed. On `db` it is a no-op.
- **`db` is hard-gated.** It needs `--force`, passes Laravel's `confirmToProceed()`, and is excluded from `all`, so a bulk tidy can never drop your tables.
- **`--force` is not the gate for user files.** It is in every CI invocation, so it is typed by habit; a `--days` or `--size` scope, or the single-purpose `--unfiltered`, is.
- **The sweep is signal-safe.** The loop checks between roots and between files, so `SIGTERM` or `SIGINT` stops it cleanly mid-directory. Without `ext-pcntl` the check always passes and a normal run is unaffected. Every run logs a completion summary with files processed, space freed and execution time.

## In the panel

The command is classified **destructive** (`RiskClassifier::DESTRUCTIVE`), so running it from the panel takes the `run` ability, the command name typed back, and a password confirmation, whichever action is chosen. Classification is by name, so the class has to fit the worst action: `db` runs `migrate:fresh`, which is itself destructive under `migrate:*`, and every other action except `cache` deletes files.

The [Tidy quick-action group](quick-actions.md#built-in-groups) pre-fills the regenerable actions, each with a dry-run twin. Nothing pre-fills `storage` or `db`; both stay reachable through the command's own form.

## Examples

```bash
# Preview, then delete, log files older than a week.
php artisan laranail::artisan-ui.tidy logs --days=7 --dry-run
php artisan laranail::artisan-ui.tidy logs --days=7 --force

# Flush the cache and the framework's optimisation caches.
php artisan laranail::artisan-ui.tidy cache --optimize --force

# Delete exports older than 90 days.
php artisan laranail::artisan-ui.tidy storage --days=90 --force

# Rebuild a local database from scratch and seed it.
php artisan laranail::artisan-ui.tidy db --seed --force
```

## Lineage

The command moved here from `laranail/toolkit`, where it was `laranail::toolkit.tidy` (`Simtabi\Laranail\Toolkit\Commands\Tidy`). Toolkit removes it in 0.3.0; use `laranail::artisan-ui.tidy` instead. The behaviour is unchanged. Only its collaborators changed: the framework's cache `Repository` and the PSR-3 `LoggerInterface` replace toolkit's own contracts.

Toolkit also shipped seven unauthenticated `GET` routes that ran `tidy all`, `optimize`, `route:cache`, `cache:clear`, `view:clear`, `config:cache` and `clear-compiled`. They were not ported. This package exists to run commands only behind an authenticated, Gate-checked `POST`, so the same capability is the Caches quick-action group, which gained `clear-compiled`, and the Tidy group.

---

[← Docs index](../../README.md#documentation)
