# Execution

The executor, `Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor`, runs an authorized, validated command through a `CommandRunner` and handles everything around it: the lock, the audit record, the events, decoration and redaction.

## Input validation

Before anything runs, `Simtabi\Laranail\ArtisanUI\Core\Validation\InputValidator` checks the request's `arguments` and `options` against the command's definition and returns a `ValidatedInput`, or throws `InvalidCommandInput` with errors keyed `arguments.<name>` / `options.<name>` (the web module answers `422`).

| Rule | Error |
|---|---|
| `arguments` and `options` must be objects keyed by name, not lists | `The arguments must be an object keyed by name.` |
| every key must be one the command defines | `The command has no argument named [x].` |
| global options (`--env`, `--verbose`, …) are refused, with or without dashes | `The global [--env] option cannot be set from the panel.` |
| required arguments must be present | `The [name] argument is required.` |
| a flag takes no value: `true`, `1`, `'1'`, `'true'`, `'on'`, `'yes'` set it; `false`, `0`, `'0'`, `'false'`, `'off'`, `'no'`, `''`, `null` leave it off | `The [--force] option is a flag and takes no value.` |
| values must be text (strings or numbers) | `The [x] field must be text.` |
| each value is at most `limits.max_value_length` characters | `… is longer than 1000 characters.` |
| a list holds at most `limits.max_array_items` values | `… takes at most 50 values.` |

Values are trimmed and empty ones dropped; a lone string is accepted for a list field; a value-optional option ticked without a value is passed as present with no value.

## The run

`CommandExecutor::execute(CommandDefinition $command, ValidatedInput $input, ?Authenticatable $actor = null, ?string $ip = null): Execution`:

1. Classify the command; a forbidden one throws `CommandNotRunnable`, whatever the caller was told.
2. Take the lock; a held lock throws `CommandBusy`.
3. Build a `RunContext` with a new ULID run id, the redacted input, the risk, the actor, the IP and the start time.
4. `RunRecorder::started()`, then dispatch `CommandExecuting`.
5. `CommandRunner::run()`.
6. If the runner caught an exception, report it as `CommandRunFailed`; if the output was truncated, log a warning.
7. Decorate the output, then redact it.
8. `RunRecorder::finished()`, then dispatch `CommandExecuted` (exit code 0) or `CommandFailed`.
9. Release the lock, whatever happened.

The returned `Execution` holds the `RunContext`, the `RunResult` and the decorated, redacted output. The raw output never leaves the executor.

## `SyncRunner`

The default `CommandRunner`, `Simtabi\Laranail\ArtisanUI\Core\Execution\SyncRunner`, runs the command in the current process through the console kernel:

```php
$exitCode = $artisan->call($command->name, [...$input->toParameters(), '--no-interaction' => true], $output);
```

- **Always `--no-interaction`.** A command that prompts would otherwise block on STDIN, hanging the worker or failing under a web server. With it, a prompt answers its default, and commands that ask for confirmation in production refuse unless `--force` is given, which is the right outcome for a web panel.
- **By name, through the kernel.** A fresh input every time; no state carries over from a previous run of the same command.
- **Bounded output.** Output goes to a `CappedOutput` buffer that stops accepting text past `limits.max_output_bytes`, cutting on a character boundary and flagging the result truncated. ANSI colour is kept for the formatter.
- **Bounded time.** `set_time_limit(limits.time_limit)` for the run; the previous limit is restored afterwards.
- **Never throws.** An exception becomes a result with status `errored`, no exit code, whatever output was written before it, and the exception attached.

## `RunResult` and statuses

| Property | Meaning |
|---|---|
| `status` | `RunStatus`: `succeeded` (exit 0), `failed` (non-zero exit), `errored` (threw), or `running` while only the start is recorded. |
| `exitCode` | `?int`; `null` when the command threw. |
| `output` | Raw output, before decoration and redaction. |
| `durationMs` | Wall time in milliseconds. |
| `truncated` | Whether the output cap was hit. |
| `exception` | The throwable, when there was one. |

`failed` and `errored` are kept apart on purpose: a non-zero exit is the command reporting a problem it understood; an exception is the command not finishing.

## Limits

| Key | Default | Applies to |
|---|---|---|
| `limits.max_value_length` | `1000` | characters per input value |
| `limits.max_array_items` | `50` | values per list field |
| `limits.max_output_bytes` | `1000000` | bytes of output kept |
| `limits.time_limit` | `120` | seconds per run |
| `limits.lock_seconds` | `300` | seconds a lock lives before it is considered abandoned |

## Locks

One cache lock per command, named `laranail-artisan-ui:run:<command>`, so two operators cannot run `migrate` at once. Every destructive command shares the single lock `laranail-artisan-ui:run:*destructive*`, so `migrate:fresh` cannot interleave with `db:wipe`. A held lock answers `409` with "[migrate] is already running. Wait for it to finish and try again."

A cache store that does not implement `LockProvider` runs the command unlocked and logs `tolerated anomaly [laranail/artisan-ui:run.lock]` at warning; doctor's "Run locks" check warns.

## The `CommandRunner` seam

```php
namespace Simtabi\Laranail\ArtisanUI\Core\Contracts;

interface CommandRunner
{
    public function run(CommandDefinition $command, ValidatedInput $input): RunResult;
}
```

`SyncRunner` is the only implementation today. The seam exists so a queued runner, which would stream output back instead of holding the request open, can be added without touching authorization, validation or audit. It is also where the test fake plugs in (`ArtisanUI::fake()`), and where an application can wrap the runner, for timing or tracing, with the container's `extend()`; see [Extending](extending.md).

A runner receives input that is already authorized and validated, and must report failure through the `RunResult` rather than by throwing.

---

[← Docs index](../../README.md#documentation)
