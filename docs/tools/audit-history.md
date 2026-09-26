# Audit and history

Three audit drivers, the cases of `Simtabi\Laranail\ArtisanUI\Enums\AuditDriver`, decide where every run is recorded; the `database` driver also powers the History screen and rerun.

## Drivers

| Driver | Recorder | Records | Needs |
|---|---|---|---|
| `log` (default) | `LogRecorder` | one structured line when a run starts, one when it ends | nothing |
| `database` | `DatabaseRecorder` | a row per run, written at start and completed at the end | the published migration |
| `none` | `NullRecorder` | nothing | nothing; doctor warns |

```dotenv
LARANAIL_ARTISAN_UI_AUDIT=database
```

A run is recorded **before** the command starts, so a run that never finishes (a fatal, a killed worker) still leaves a record that it began. Recorders never throw: a broken audit store must not stop an operator from running a command, and must not hide that it ran.

## The log driver

Two lines on `audit.channel` (or the default channel):

| Message | Level | Context |
|---|---|---|
| `laranail/artisan-ui: run started` | info | `run_id`, `command`, `input` (redacted), `risk`, `user`, `ip` |
| `laranail/artisan-ui: run finished` | info on success, warning otherwise | the above plus `status`, `exit_code`, `duration_ms`, `output_bytes`, `truncated`, `error` (the exception class only) |

Output is not logged, only its size. If the log channel itself fails, the write falls back to PHP's `error_log()`.

## The database driver

Publish and run the migration (the install command offers to):

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-migrations
php artisan migrate
```

### `laranail_artisan_ui_runs`

| Column | Type | Meaning |
|---|---|---|
| `id` | ULID, primary | The run id, shared with the log lines and the events. |
| `user_type`, `user_id` | string, nullable | The actor's class and identifier. Indexed together. |
| `command` | string, indexed | The command name. |
| `arguments`, `options` | JSON, nullable | The input, with secret-named keys masked. |
| `risk` | string | The `CommandRisk` value. |
| `status` | string, indexed | `running`, then `succeeded`, `failed` or `errored`. |
| `exit_code` | integer, nullable | `null` when the command threw. |
| `output` | long text, nullable | The decorated, redacted output; `null` when `audit.store_output` is false. |
| `output_truncated` | boolean | Whether the output cap was hit. |
| `error` | text, nullable | The exception message, redacted, at most 2000 characters. |
| `duration_ms` | unsigned integer, nullable | Wall time. |
| `ip` | string(45), nullable | The client address. |
| `started_at`, `finished_at` | timestamps | Bounds of the run. |
| `created_at`, `updated_at` | timestamps | `created_at` is indexed for pruning. |

The Eloquent model is `Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun`, with `risk` and `status` cast to their enums. It uses `audit.connection` when set.

### When the table disappears

`migrate:fresh` and `db:wipe` drop the table, and they are exactly the commands worth auditing. When the start row cannot be written, or no longer exists at the finish, the record goes to the log instead, so the run is never unaccounted for:

- a write that throws goes through package-tools' `FailurePolicy` as **degradable**: it is reported, and `BootReport` marks `laranail/artisan-ui:audit.database` degraded, which `boot:health` and `laranail::package-tools.doctor` show;
- a finish that finds no row logs `tolerated anomaly [laranail/artisan-ui:audit.database]` at warning and writes both the start and the finish to the log.

To keep the history itself, point `audit.connection` at a connection those commands do not touch:

```dotenv
LARANAIL_ARTISAN_UI_AUDIT_CONNECTION=audit
```

The published migration creates the table on that connection.

## Retention and pruning

`CommandRun` is mass-prunable: rows whose `created_at` is older than `audit.retention_days` (default 90) are removed by the framework's `model:prune`.

With the `database` driver and `audit.schedule_prune` true (the default), the package registers on the scheduler:

```php
$schedule->command('model:prune', ['--model' => [CommandRun::class]])
    ->daily()
    ->name('laranail-artisan-ui:prune-runs')
    ->withoutOverlapping();
```

The application's scheduler still has to be running. To schedule it yourself, set `audit.schedule_prune` to false and add the same command; doctor's "Audit pruning" check warns when nothing prunes the table.

## The history screen

`GET {path}/history`, route `laranail-artisan-ui.history`:

- exists only with the `database` driver; otherwise `404`;
- needs the `view-history` ability; otherwise `403`, logged and dispatched as `AccessDenied`;
- lists runs newest first, 25 per page, filterable by exact command name and by status;
- each run expands to its run id, error and output, rendered from segments like the live output.

The home screen links to it only when both conditions hold.

## Rerun

Each history entry has a **Rerun** link to the command's form, pre-filled with the recorded arguments and options. It never runs anything by itself.

Recorded input is redacted, so a masked secret must be typed again: any pre-fill value equal to `redaction.mask` is dropped rather than put in the form, where submitting it would send the mask as the value. Pre-filling also keeps only keys the command still defines.

---

[← Docs index](../../README.md#documentation)
