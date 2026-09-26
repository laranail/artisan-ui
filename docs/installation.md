# Installation

Requirements, the VCS repositories Composer needs, and the one install command that publishes the config and the audit migration.

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| `laranail/package-tools` | `^0.1` (pulled in automatically) |
| `laranail/console` | `^0.1` (pulled in automatically) |
| `laranail/enumerator` | `^0.1` (pulled in automatically) |

The panel also needs a **session-based auth guard** (its forms, CSRF token and password confirmation all live in the session) and, for per-command locking, a cache store that supports locks. `laranail::artisan-ui.doctor` checks both.

## Add the VCS repositories

`laranail/artisan-ui` is not on Packagist, and the laranail family resolves its own packages through git rather than Packagist. Composer ignores a dependency's own `repositories`, so the application has to declare every `laranail/*` package in the chain:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/laranail/artisan-ui" },
        { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
        { "type": "vcs", "url": "https://github.com/laranail/console" },
        { "type": "vcs", "url": "https://github.com/laranail/enumerator" }
    ]
}
```

## Require the package

If the panel is only for local work, require it as a development dependency. The service provider then does not exist in a production install (`composer install --no-dev`), which is a stronger guarantee than any configuration switch:

```bash
composer require --dev laranail/artisan-ui
```

If the panel has to be reachable on a deployed environment, require it normally and read [Security](security.md) before switching it on:

```bash
composer require laranail/artisan-ui
```

The `ArtisanUIServiceProvider` is auto-discovered. No facade alias is registered; import `Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI` where you need it.

## Run the install command

```bash
php artisan laranail::artisan-ui.install
```

In order, it:

1. publishes the config to `config/laranail/artisan-ui.php`;
2. publishes the migration for the `laranail_artisan_ui_runs` table (only needed for the `database` audit driver);
3. asks whether to run the migrations;
4. prints the two `Gate::define()` calls you need before anyone can open the panel.

Everything can also be published individually:

| Tag | Publishes | Destination |
|---|---|---|
| `laranail::artisan-ui-config` | `config/artisan-ui.php` | `config/laranail/artisan-ui.php` |
| `laranail::artisan-ui-migrations` | the run-table migration | `database/migrations/` |
| `laranail::artisan-ui-views` | `resources/views` | `resources/views/vendor/laranail/artisan-ui` |
| `laranail::artisan-ui-translations` | `resources/lang` | `lang/vendor/laranail/artisan-ui` |
| `laranail::artisan-ui-assets` | `resources/dist` | `public/vendor/artisan-ui` |

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-config
```

## After a tag move

The package is pre-1.0 and ships on a single moving `v0.1.0` tag. Composer caches the archive per tag, so after the tag moves an update can resolve `v0.1.0` and still unpack the old code. Clear the cache first:

```bash
composer clear-cache && composer update laranail/artisan-ui
```

See [Release](release.md).

## Next

Nothing is reachable yet: the panel is disabled, allowed only in `local`, and every Gate ability denies. [Getting started](getting-started.md) switches it on.

---

[← Docs index](../README.md#documentation)
