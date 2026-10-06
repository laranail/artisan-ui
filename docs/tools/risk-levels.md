# Risk levels

Four risk classes, the `Simtabi\Laranail\ArtisanUI\Enums\CommandRisk` enum, decide what it takes to run a command; `Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier` assigns one to every command by name.

## The classes

| Class | Value | Badge | What it takes to run |
|---|---|---|---|
| Safe | `safe` | none | The `run` ability. |
| Writes files | `writes_files` | amber | The `run` ability, and an environment listed in `risk.writes_files_environments` (default `['local']`); otherwise `403`. |
| Destructive | `destructive` | red | The `run` ability, the command name typed back, and a password confirmation within the timeout. |
| Forbidden | `forbidden` | none | Never listed, never runnable: `404`. |

Classification is by name. It is a speed bump against the obvious mistakes, not a sandbox: a custom command can do anything, which is why the `run` ability is asked for every command whatever its class.

## Precedence

The first list that matches decides, in this order:

**safe → forbidden → destructive → writes_files**

Anything that matches none is **safe**. Because `safe` is checked first, an entry there carves an exception out of any other list, including the package defaults: `migrate:status` is safe although `migrate:*` is destructive.

## Default lists

These are the `RiskClassifier` constants. Configuration under `risk.*` is merged **over** them; it never replaces them, so upgrading the package can classify a newly dangerous framework command without the application having to notice.

### Safe (`RiskClassifier::SAFE`)

| Pattern | Why |
|---|---|
| `migrate:status` | Only reads; carved out of `migrate:*`. |

### Forbidden (`RiskClassifier::FORBIDDEN`)

Long-running processes and interactive shells. Run from a web request, each holds a PHP worker until the time limit kills it, and several never exit at all.

| Pattern | Pattern |
|---|---|
| `config:show` | `serve` |
| `tinker` | `db` |
| `dev` | `pail` |
| `queue:work` | `queue:listen` |
| `schedule:work` | `octane:*` |
| `horizon` | `reverb:start` |
| `sail:*` | `pulse:check` |
| `pulse:work` | `nightwatch:agent` |
| `invoke-serialized-closure` | `schedule:finish` |

`config:show` is here for a different reason: it prints configuration values, credentials included, in a dotted table the redactor cannot reliably recognise. An application that wants it can list it under `risk.safe`.

`invoke-serialized-closure` and `schedule:finish` are framework internals, hidden for a reason. The first `unserialize()`s its argument, which from a web form is object injection, so it stays forbidden even when `commands.include_hidden` is on.

### Destructive (`RiskClassifier::DESTRUCTIVE`)

Commands that destroy data, rotate secrets or take the application down.

| Pattern | Pattern |
|---|---|
| `migrate:*` | `db:wipe` |
| `db:seed` | `key:generate` |
| `down` | `env:decrypt` |
| `queue:clear` | `queue:flush` |
| `queue:forget` | `queue:prune-batches` |
| `queue:prune-failed` | `model:prune` |
| `auth:clear-resets` | `storage:unlink` |
| `schedule:run` | `schedule:interrupt` |
| `schedule:test` | `queue:retry` |
| `queue:retry-batch` | `env:encrypt` |
| `horizon:terminate` | `horizon:clear` |
| `horizon:purge` | `laranail::artisan-ui.tidy` |

`down` is destructive because running it from the panel locks the panel out too, unless the panel's path is exempt from maintenance mode or `down --secret` is used. `key:generate` rotates `APP_KEY`, which signs everyone out and makes previously encrypted data unreadable. `schedule:test` runs whichever scheduled task is picked, whatever it does; `queue:retry` re-dispatches jobs that failed for a reason; `env:encrypt` rewrites `.env.encrypted`. This package's own [`laranail::artisan-ui.tidy`](tidy.md) deletes files under storage and its `db` action runs `migrate:fresh`; one name covers every action, so it takes the class of the worst.

### Writes files (`RiskClassifier::WRITES_FILES`)

Commands that change files in the application.

| Pattern | Pattern |
|---|---|
| `make:*` | `vendor:publish` |
| `storage:link` | `lang:publish` |
| `stub:publish` | `config:publish` |
| `install:*` | `ide-helper:*` |

## Adding your own

```php
'risk' => [
    'safe'         => ['make:test'],          // an exception carved out of make:*
    'forbidden'    => ['app:daemon'],         // your own long-running command
    'destructive'  => ['app:purge-*'],
    'writes_files' => ['app:generate-*'],
    'writes_files_environments' => ['local', 'staging'],
],
```

## Reading a classification

```php
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

ArtisanUI::risk('migrate:fresh') === CommandRisk::Destructive;   // true
ArtisanUI::risk('migrate:fresh')->requiresConfirmation();        // true
ArtisanUI::risk('serve')->isRunnable();                          // false
```

Or for the whole application: `php artisan laranail::artisan-ui.policy --risk=writes_files`.

---

[← Docs index](../../README.md#documentation)
