# Quick actions

Seven built-in groups of quick actions, served by `Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog`, put common commands one click from a pre-filled form on the home screen.

## A quick action never runs anything

A quick action is a link to a command's form with values filled in through the query string. Pressing it opens the form; running it takes a press of **Run**, which goes through the one execute endpoint and every check that guards it, the destructive confirmation included. There is no endpoint that runs a quick action directly.

An action is dropped when its command is not listed, so a quick action can never surface a command the policy or the risk rules hide. A group left with no actions is not shown.

## Built-in groups

| Key | Label | Actions |
|---|---|---|
| `caches` | Caches | `optimize`, `optimize:clear`, `cache:clear`, `config:cache`, `config:clear`, `route:cache`, `route:clear`, `view:cache`, `view:clear`, `event:cache`, `event:clear`, `clear-compiled` |
| `storage` | Storage | `storage:link`, `storage:link --force`, `storage:unlink` |
| `database` | Database | `migrate:status`, `migrate`, `migrate --seed`, `migrate:rollback`, `migrate:rollback --step=1`, `migrate:fresh --seed`, `db:seed`, `db:show` |
| `tables` | Framework tables | `make:cache-table`, `make:notifications-table`, `make:queue-table`, `make:queue-failed-table`, `make:queue-batches-table`, `make:session-table` |
| `generators` | Generators | `make:model` with migration, factory, seeder and controller; `make:controller` resource, API and invokable; `make:migration`, `make:request`, `make:policy`, `make:job`, `make:event`, `make:listener`, `make:mail`, `make:notification`, `make:middleware`, `make:command`, `make:seeder`, `make:factory` |
| `maintenance` | Maintenance | `key:generate`, `down --secret=`, `up`, `about` |
| `tidy` | Tidy | `laranail::artisan-ui.tidy` with `cache --dry-run`, `cache --force`, `logs --days=7 --dry-run`, `logs --days=7 --force`, `temp --dry-run`, `temp --force` |

The maintenance group's `down` action pre-fills an empty `--secret` to prompt for one: without a secret, `down` locks the panel out too.

The tidy group covers only [tidy](tidy.md)'s regenerable actions, each with a dry-run twin, and scopes log deletion to files older than seven days. It has nothing for `storage`, which sweeps user uploads, or `db`, which runs `migrate:fresh`; both remain reachable through the command's own form. The non-dry-run actions pre-fill `--force`, because the panel runs every command non-interactively and tidy's confirmation would otherwise answer no and delete nothing. The command is destructive, so every one of them, previews included, still asks for the command name and the password.

## The duplicate-migration guard

`Simtabi\Laranail\ArtisanUI\Core\Presets\MigrationExistsGuard` greys out a framework-table generator when `database/migrations` already holds a `*_create_<table>_table.php` file, and shows why:

| Command | Table |
|---|---|
| `make:cache-table` | `cache` |
| `make:notifications-table` | `notifications` |
| `make:queue-table` | `jobs` |
| `make:queue-failed-table` | `failed_jobs` |
| `make:queue-batches-table` | `job_batches` |
| `make:session-table` | `sessions` |

Running one twice would produce two migrations for the same table and a failing `migrate`.

## Adding groups

From configuration:

```php
'presets' => [
    'groups' => [
        'deploy' => [
            'label'       => 'Deploy',
            'description' => 'After a release.',
            'actions'     => [
                ['label' => 'Warm caches', 'command' => 'optimize'],
                ['label' => 'Seed roles', 'command' => 'db:seed', 'options' => ['class' => 'RoleSeeder']],
            ],
        ],
    ],
],
```

At runtime, from a service provider:

```php
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

ArtisanUI::quickActions('reports', 'Reports', [
    new QuickAction('Rebuild monthly report', 'app:report', arguments: ['period' => 'monthly']),
    new QuickAction('Rebuild and email', 'app:report', options: ['email' => true], description: 'Sends to finance.'),
], description: 'Scheduled reports, run on demand.');
```

Groups appear in this order: built-in, configured, then registered at runtime. `ArtisanUI::quickActions()` returns the manager, so registrations chain.

## `QuickAction`

| Parameter | Type | Meaning |
|---|---|---|
| `label` | `string` | The link text. Required, non-empty. |
| `command` | `string` | The command to pre-fill. Required, non-empty. |
| `arguments` | `array<string, string\|list<string>>` | Argument values. |
| `options` | `array<string, bool\|string\|list<string>>` | Option values; `true` ticks a flag. |
| `description` | `?string` | Shown as the link's title. |

A configured action missing its `label` or `command` is skipped. Pre-filling keeps only keys the command defines and only text values; everything is validated on submit like any other input.

## Switching them off

```php
'presets' => ['enabled' => false],
```

---

[← Docs index](../../README.md#documentation)
