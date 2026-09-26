# Output

Three stages turn a command's raw output into what the panel shows and records: decorators rewrite it, `SecretRedactor` scrubs it, and `AnsiFormatter` splits it into styled text segments.

## Order

```text
runner output (raw, capped)
  → decorators, every matching one, in order     DecoratorRegistry
  → redaction                                     SecretRedactor::scrub()
  → recorded, passed to listeners, returned       RunRecorder, events, Execution
  → split into {text, classes} segments           AnsiFormatter::segments()
  → rendered with textContent                     browser / Blade {{ }}
```

Decorators run **before** redaction, so whatever a decorator adds is still scrubbed; a decorator cannot leak a secret. The recorder, the event listeners and the browser all see the same decorated, redacted text.

## Decorators

An output decorator implements `Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator`:

```php
interface OutputDecorator
{
    public function decorate(string $output, CommandDefinition $command, RunResult $result): string;
}
```

Register one per command pattern, in configuration or at runtime:

```php
// config/laranail/artisan-ui.php
'decorators' => [
    'about' => App\ArtisanUI\AboutDecorator::class,
],
```

```php
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

ArtisanUI::decorate('queue:*', App\ArtisanUI\QueueDecorator::class);
ArtisanUI::decorate('about', new App\ArtisanUI\AboutDecorator('prefix'));
```

`Simtabi\Laranail\ArtisanUI\Core\Output\DecoratorRegistry` applies **every** decorator whose pattern matches, configuration entries first, then runtime registrations, each in declaration order, feeding each one the previous one's output. Class names are resolved through the container, so a decorator can take dependencies. A class that does not implement the contract throws.

A decorator that throws is a fail-closed degrade. The output is **withheld** rather than shown undecorated, because a decorator may exist precisely to hide something. The operator sees:

```text
[laranail/artisan-ui: output withheld because an output decorator failed; see the application log]
```

The failure is reported and recorded in package-tools' `BootReport` as `laranail/artisan-ui:output.decorate`, and the run is still recorded.

## Redaction

`Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor` makes three passes over output, cheapest first:

| Pass | Masks | Example |
|---|---|---|
| 1. Known secret values | the value of every environment variable (`$_SERVER`, `$_ENV`, `getenv()`) whose name matches `redaction.keys`, and of every credential in the loaded config (`app.key`, `app.previous_keys.*`, and keys whose last segment is `password`, `secret`, `token`, `api_key`, `client_secret`, `private_key`, `dsn` and similar; never a class name, never `app.aliases`), wherever it appears. Under `config:cache` the config is the only source. For a `base64:` value, the bare value too. Values shorter than `redaction.min_scrub_length` (8 for config) are skipped. Longest first. When output was cut at the cap, a trailing fragment of at least 8 characters that starts a secret is masked too. | `tok_live_0123456789` → `••••••••` |
| 2. `NAME=value` lines | a line whose name matches `redaction.keys`, with `=` or `:`, optionally after `export` | `DB_PASSWORD=hunter2` → `DB_PASSWORD=••••••••` |
| 3. URL credentials | the password in `scheme://user:password@host` | `redis://default:s3cr3t@cache:6379` → `redis://default:••••••••@cache:6379` |

Names are matched case-insensitively against the wildcard patterns in `redaction.keys` (defaults: `*password*`, `*secret*`, `*token*`, `*_key`, `key`, `*private*`, `*dsn*`, `*credential*`).

Recorded input is masked **by key**: an argument or option whose name matches is stored as the mask whatever its value; flags (`true`/`false`) and `null` are left alone. The same scrub is applied to the exception message the database recorder stores.

Text a command prints with `echo`, `print` or `var_dump` is captured into the same output, in the order it was written relative to console output, so it is capped and redacted like the rest; `ob_clean()` inside a command still discards. A command that removes the capture buffer (`ob_end_clean()`) gets a note appended, and a warning is logged.

> Redaction is best effort. A secret printed in a shape these passes do not recognise, or shorter than the minimum, gets through. A command that prints secrets should be denied rather than relied on to be masked; `config:show` is forbidden by default for that reason.

## ANSI segments

`Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter::segments()` turns coloured console text into a list of `{text, classes}` pairs, merging adjacent segments with the same classes. It produces no HTML.

| Handled | Result |
|---|---|
| SGR bold, dim, italic, underline and their resets | `lau-bold`, `lau-dim`, `lau-italic`, `lau-underline` |
| the 8 foreground colours and their bright variants | `lau-fg-red`, `lau-fg-bright-cyan`, ... |
| the 8 background colours and their bright variants | `lau-bg-yellow`, `lau-bg-bright-black`, ... |
| 256-colour and truecolour parameters | consumed and ignored |
| OSC sequences (hyperlinks, titles), cursor movement, erase line, lone escapes | removed |
| carriage-return progress redraws | only the final state of each line kept |

`toPlain()` returns the same text with every escape removed.

In the browser each segment becomes a `<span>` whose `textContent` is the text; a class list is applied only when it matches the `lau-*` shape the server produces, so even a tampered response cannot inject arbitrary classes. The History view renders stored output the same way through Blade's escaping `{{ }}`.

## The run response

The run endpoint returns:

| Field | Meaning |
|---|---|
| `run_id` | The ULID shared by the audit record, the log lines and the events. |
| `command`, `risk` | The command name and its risk class value. |
| `status`, `status_label`, `success` | `succeeded`, `failed` or `errored`; its label; whether it succeeded. |
| `exit_code` | The exit code, or `null` when the command threw. |
| `duration_ms` | Wall time. |
| `truncated` | Whether the output hit `limits.max_output_bytes`. |
| `output` | The segments. |
| `error` | `null`, or a generic message naming the run id when the command threw. A Symfony console input error ("Not enough arguments") is shown instead, redacted, because it is a validation message about what was typed. |

---

[← Docs index](../../README.md#documentation)
