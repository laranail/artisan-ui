# Commands

Four Artisan commands ship with the package, all under the family's `laranail::artisan-ui.*` names: `install`, `doctor`, `policy` and `tidy`.

| Command | Purpose |
|---|---|
| `laranail::artisan-ui.install` | Publish the config and migration, offer to migrate, explain the abilities. |
| `laranail::artisan-ui.doctor` | Report exposure, abilities, guard, maintenance mode, audit, locks, assets and Octane. |
| `laranail::artisan-ui.policy` | Show, for every command, whether the panel lists it and its risk class. |
| `laranail::artisan-ui.tidy` | Path-confined maintenance: flush the cache, sweep logs, temp and scoped uploads, or rebuild the database. See [Tidy](tidy.md). |

There are no bare aliases such as `artisan-ui:install`; see the family naming rules.

## `laranail::artisan-ui.install`

```bash
php artisan laranail::artisan-ui.install
```

No options beyond Laravel's global ones. In order it:

1. publishes `config/laranail/artisan-ui.php`;
2. publishes the `create_laranail_artisan_ui_runs_table` migration;
3. asks whether to run the migrations now;
4. prints what to do next:

```text
Nobody can open the panel until you define who may. In a service provider:

    Gate::define('laranail-artisan-ui.access', fn ($user) => $user->isAdmin());
    Gate::define('laranail-artisan-ui.run', fn ($user, $command, $input) => $user->isAdmin());

Then set LARANAIL_ARTISAN_UI_ENABLED=true and run `php artisan laranail::artisan-ui.doctor`.
```

The steps can also be done by hand with the publish tags listed in [Installation](../installation.md).

## `laranail::artisan-ui.doctor`

```bash
php artisan laranail::artisan-ui.doctor [--json] [--strict]
```

| Option | Effect |
|---|---|
| `--json` | Emit the report as JSON. |
| `--strict` | Treat warnings as failures. |

The command exits non-zero when any check fails (or warns, with `--strict`). The same checks appear under the package in `laranail::package-tools.doctor`.

| Check | Passes when | Otherwise |
|---|---|---|
| Environments | `production` and `*` are not in `environments` | **fails** |
| Enabled | the panel is enabled (reports the path) | skipped while disabled; warns when enabled in `production` |
| Gate abilities | the application defined `access` and `run` | warns "Nobody can use the panel yet" and lists the undefined ones |
| Auth guard | the guard exists and uses the `session` driver | fails for an unknown guard, warns for another driver |
| Maintenance mode | the panel path is exempt from maintenance mode | warns that `down` would lock the panel out |
| Audit trail | the driver is `log`, or `database` with its table present | warns for `none`; fails when the table is missing |
| Audit pruning | `model:prune` is scheduled for the run table | warns; skipped for other drivers |
| Run locks | the cache store is reachable and supports locks | fails when unreachable (rate limiting and locks would 500 every run); warns when it cannot lock |
| Assets | the bundle is built (and, in `published` mode, published and current) | fails when unbuilt; warns when the published copy is stale |
| Octane | Octane is not installed | warns that commands share the worker's state |

Example, with the panel enabled and no abilities defined yet (messages shortened):

```text
+---+------------------+---------------------------------------------------------------+
|   | Check            | Result                                                        |
+---+------------------+---------------------------------------------------------------+
| ✓ | Environments     | Allowed in: local.                                            |
| ✓ | Enabled          | Enabled at /artisan.                                          |
| ! | Gate abilities   | Nobody can use the panel yet: define laranail-artisan-ui...   |
| ✓ | Auth guard       | Authenticating with the [web] session guard.                  |
| ! | Maintenance mode | Running `down` from the panel will lock the panel out too...  |
| ✓ | Audit trail      | Runs are logged to the default channel.                       |
| · | Audit pruning    | Only applies to the database audit driver.                    |
| ✓ | Run locks        | The cache store is reachable and supports locks.              |
| ✓ | Assets           | Built, and served in route mode.                              |
| · | Octane           | Octane is not installed.                                      |
+---+------------------+---------------------------------------------------------------+
6 passed, 2 warning(s), 0 failure(s), 2 skipped.
```

`✓` passed, `!` warning, `·` skipped.

## `laranail::artisan-ui.policy`

```bash
php artisan laranail::artisan-ui.policy [--risk=<class>] [--listed] [--json]
```

| Option | Effect |
|---|---|
| `--risk=` | Only commands in this risk class: `safe`, `writes_files`, `destructive` or `forbidden`. An unknown class exits with an error. |
| `--listed` | Only commands the panel lists. |
| `--json` | Emit the rows as JSON: `command`, `listed`, `risk`, `reason`. |

It reads the same policy and classifier the panel uses, so the effect of the allow, deny and risk lists can be checked before anyone opens the panel. The "Why not" column reads `forbidden`, `hidden` or `allow/deny list`.

```text
$ php artisan laranail::artisan-ui.policy --risk=destructive
+---------------------+--------+-------------+---------+
| Command             | Listed | Risk        | Why not |
+---------------------+--------+-------------+---------+
| auth:clear-resets   | yes    | destructive |         |
| db:seed             | yes    | destructive |         |
| db:wipe             | yes    | destructive |         |
| down                | yes    | destructive |         |
| key:generate        | yes    | destructive |         |
| migrate             | yes    | destructive |         |
| migrate:fresh       | yes    | destructive |         |
| ...                 |        |             |         |
+---------------------+--------+-------------+---------+

21 commands, 21 listed.
```

```json
[
    { "command": "serve", "listed": false, "risk": "forbidden", "reason": "forbidden" }
]
```

## `laranail::artisan-ui.tidy`

```bash
php artisan laranail::artisan-ui.tidy [cache|logs|temp|storage|db|all] [--days=] [--size=] [--seed] [--optimize] [--dry-run] [--unfiltered] [--force]
```

Moved here from `laranail/toolkit` (`laranail::toolkit.tidy`). Every deletion is confined to `storage_path()`, an unscoped sweep of user uploads is refused, and `db` (`migrate:fresh`) needs `--force` and is never part of `all`. The full reference is [Tidy](tidy.md).

## Also registered

- An **About** section, "Artisan UI", in `php artisan about`: enabled, path, environments and audit driver.
- With the database driver, a daily `model:prune` for the run table on the scheduler, named `laranail-artisan-ui:prune-runs`; see [Audit and history](audit-history.md).

---

[← Docs index](../../README.md#documentation)
