# Configuration

Every key under `laranail.artisan-ui`, with its default, its environment variable where it has one, and what it decides.

## Where the configuration lives

The package reads `config('laranail.artisan-ui.*')`. Publish the file to change it:

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-config
```

That writes `config/laranail/artisan-ui.php`. Every read goes through `Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig`, which fails closed: a malformed value resolves to the most restrictive reading, never to an open one. Values are read on every call, so a runtime `config()->set()` (in a test, for example) takes effect immediately.

## Exposure

| Key | Default | Env | Meaning |
|---|---|---|---|
| `enabled` | `false` | `LARANAIL_ARTISAN_UI_ENABLED` | Master switch. While false the routes are not registered, and a stale cached route answers `404`. Only a literal `true` enables. |
| `domain` | `null` | `LARANAIL_ARTISAN_UI_DOMAIN` | Host the routes are bound to. `null` means any host. |
| `path` | `'artisan'` | `LARANAIL_ARTISAN_UI_PATH` | URL prefix. Slashes are trimmed; an empty value falls back to `artisan`. |
| `middleware` | `['web']` | | Middleware applied **before** the package's own. `SecurityHeaders` and `EnsureArtisanUIAccess` are always appended, so removing entries here cannot remove them. Keep `web` (or a group containing it): the panel needs a session and CSRF. |
| `guard` | `null` | `LARANAIL_ARTISAN_UI_GUARD` | Auth guard whose user is checked. `null` uses the application default. Must be a session guard; doctor warns otherwise. |
| `environments` | `['local']` | | Environments the panel answers in, checked independently of the Gate. `['*']` allows every environment. An empty list allows none. Doctor **fails** when `production` or `*` is listed. |
| `allowed_ips` | `[]` | | Optional allowlist of addresses and CIDR ranges, IPv4 and IPv6. Empty allows any address. A malformed entry denies everyone rather than opening the panel. |
| `log_channel` | `null` | `LARANAIL_ARTISAN_UI_LOG_CHANNEL` | Log channel for denied access attempts. `null` uses the default channel. |

## Commands

| Key | Default | Meaning |
|---|---|---|
| `commands.allow` | `null` | `null` lists every command; a list restricts the panel to exactly the commands matching it. An empty list allows **nothing**. |
| `commands.deny` | `[]` | Commands never listed or runnable. The deny list always wins over the allow list. |
| `commands.include_hidden` | `false` | Whether commands marked hidden are listed. |

Patterns use `Str::is()` wildcards, and a namespace pattern such as `migrate:*` also matches the bare `migrate`. See [Command policy](tools/command-policy.md).

## Risk

| Key | Default | Meaning |
|---|---|---|
| `risk.safe` | `[]` | Extra patterns classified safe. Checked first, so an entry here carves an exception out of any list below, including the package defaults. |
| `risk.forbidden` | `[]` | Extra patterns never listed or runnable. |
| `risk.destructive` | `[]` | Extra patterns that need the typed confirmation and a password confirmation. |
| `risk.writes_files` | `[]` | Extra patterns that run only in `risk.writes_files_environments`. |
| `risk.writes_files_environments` | `['local']` | Environments in which writes-files commands may run. `['*']` allows all. |

Entries are merged over the package defaults, never replacing them. The full default lists are in [Risk levels](tools/risk-levels.md).

## Confirmation

| Key | Default | Meaning |
|---|---|---|
| `confirmation.require_password` | `true` | Whether a destructive command also needs a recent password confirmation. The typed command name is always required. |
| `confirmation.password_timeout` | `null` | Seconds a confirmation stays valid. `null` follows `auth.password_timeout` (Laravel's default is 10800). |

The confirmation is the `auth.password_confirmed_at` session key Laravel's own `password.confirm` middleware writes, so a confirmation made elsewhere in the application counts here, and one made here counts there.

## Limits

| Key | Default | Meaning |
|---|---|---|
| `limits.max_value_length` | `1000` | Maximum characters in any single argument or option value. |
| `limits.max_array_items` | `50` | Maximum values in a list-valued argument or option. |
| `limits.max_output_bytes` | `1000000` | Output past this many bytes is dropped and the run is flagged truncated. Also bounds memory. |
| `limits.time_limit` | `120` | Seconds passed to `set_time_limit()` for the duration of the run; the previous limit is restored afterwards. |
| `limits.lock_seconds` | `300` | Seconds a run's lock lives if the process dies without releasing it. Never less than `time_limit + 10`. A run killed by the time limit or a fatal error is recorded as errored and releases its lock from a shutdown handler, so this is only a backstop. Not capped from above: the time limit counts CPU time on Linux, so a command waiting on the database can outlast it by the clock. |

A non-numeric or non-positive value falls back to the default.

## Rate limit

| Key | Default | Meaning |
|---|---|---|
| `rate_limit.per_minute` | `30` | Executions per user per minute, on the run endpoint only. Keyed by user id, or by IP when there is none. |

Password confirmation has its own fixed limit of five attempts per minute per user.

## Audit

| Key | Default | Env | Meaning |
|---|---|---|---|
| `audit.driver` | `'log'` | `LARANAIL_ARTISAN_UI_AUDIT` | `log`, `database` or `none`. An unknown value falls back to `log`. |
| `audit.channel` | `null` | `LARANAIL_ARTISAN_UI_AUDIT_CHANNEL` | Log channel for the `log` driver and for the `database` driver's fallback. `null` uses the default channel. |
| `audit.connection` | `null` | `LARANAIL_ARTISAN_UI_AUDIT_CONNECTION` | Database connection for the run table. Point it somewhere `migrate:fresh` does not reach if you run database commands from the panel. |
| `audit.retention_days` | `90` | | Runs older than this are removed by `model:prune`. |
| `audit.schedule_prune` | `true` | | Register a daily `model:prune` for the run table on the scheduler when the driver is `database`. |
| `audit.store_output` | `true` | | Whether the `database` driver stores the (decorated, redacted) output. |

See [Audit and history](tools/audit-history.md).

## Redaction

| Key | Default | Meaning |
|---|---|---|
| `redaction.keys` | `*password*`, `*secret*`, `*token*`, `*_key`, `key`, `*private*`, `*dsn*`, `*credential*` | Wildcard patterns matched case-insensitively against option names, environment variable names and `NAME=value` lines. |
| `redaction.mask` | `'••••••••'` | Replacement text for a redacted value. An empty value falls back to the default. |
| `redaction.min_scrub_length` | `6` | Environment values shorter than this are not scrubbed from output, so a short password does not blank every occurrence of a common word. |

Redaction is best effort; see [Output](tools/output.md) and [Security](security.md).

## Decorators

| Key | Default | Meaning |
|---|---|---|
| `decorators` | `[]` | `command pattern => class` implementing `Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator`. Classes are resolved through the container. |

```php
'decorators' => [
    'about' => App\ArtisanUI\AboutDecorator::class,
],
```

## Quick actions

| Key | Default | Meaning |
|---|---|---|
| `presets.enabled` | `true` | Whether the Quick actions sidebar is shown at all. |
| `presets.groups` | `[]` | Extra groups, keyed by group key. |

```php
'presets' => [
    'enabled' => true,
    'groups' => [
        'deploy' => [
            'label' => 'Deploy',
            'description' => 'After a release.',
            'actions' => [
                ['label' => 'Warm caches', 'command' => 'optimize', 'description' => 'Config, routes, views, events.'],
                ['label' => 'Seed roles', 'command' => 'db:seed', 'options' => ['class' => 'RoleSeeder']],
            ],
        ],
    ],
],
```

An action needs a non-empty `label` and `command`; `arguments`, `options` and `description` are optional. See [Quick actions](tools/quick-actions.md).

## Theme and assets

| Key | Default | Env | Meaning |
|---|---|---|---|
| `theme` | `'default'` | | View directory under `resources/views/themes`. Must be a lowercase slug (`[a-z0-9]` with single hyphens); anything else falls back to `default`. |
| `assets.mode` | `'route'` | `LARANAIL_ARTISAN_UI_ASSETS` | `route` serves the stylesheet and script from the package through a content-hashed, immutably cached URL. `published` serves them from `public/vendor/artisan-ui`. |
| `assets.route` | `'vendor/laranail-artisan-ui'` | | URL prefix of the asset route in `route` mode. |

See [Web UI](tools/web-ui.md).

---

[← Docs index](../README.md#documentation)
