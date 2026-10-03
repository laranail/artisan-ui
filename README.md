# laranail/artisan-ui

[![Tests](https://github.com/laranail/artisan-ui/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/artisan-ui/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/artisan-ui/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/artisan-ui/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

`laranail/artisan-ui` is not on Packagist, so there is no registry-version badge to show; [Install](#install) covers the VCS route.

> A secure web panel for running Artisan commands, authorized per user and per command through Gate abilities, risk-classified, audited, with quick actions and run history.

Targets PHP `^8.4.1 || ^8.5` on Laravel `^13.0`. Built on `laranail/package-tools`, `laranail/console` and `laranail/enumerator`. Derived from [`lorisleiva/artisan-ui`](https://github.com/lorisleiva/artisan-ui), with features from [`pabloleone/artisan-ui`](https://github.com/pabloleone/artisan-ui) and [`dev-arindam-roy/artisan-ui`](https://github.com/dev-arindam-roy/artisan-ui).

## Install

Add the VCS repositories (the laranail family does not resolve through Packagist), then require the package. Use `--dev` if the panel is only for local work, so it does not exist in production at all:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/laranail/artisan-ui" },
    { "type": "vcs", "url": "https://github.com/laranail/package-tools" },
    { "type": "vcs", "url": "https://github.com/laranail/console" },
    { "type": "vcs", "url": "https://github.com/laranail/enumerator" }
]
```

```bash
composer require --dev laranail/artisan-ui
```

```bash
php artisan laranail::artisan-ui.install
```

Then decide who may use it. Nobody can until you do:

```php
Gate::define('laranail-artisan-ui.access', fn (User $user) => $user->isAdmin());
Gate::define('laranail-artisan-ui.run', fn (User $user, $command, array $input) => $user->isAdmin());
```

## Quick start guide and usage

### Getting started

Every default fails closed, so the panel is unreachable until three things are true:

1. `php artisan laranail::artisan-ui.install` has run (see Install). It publishes `config/laranail/artisan-ui.php` and the migration for the `laranail_artisan_ui_runs` table, which only the `database` audit driver needs.
2. The `laranail-artisan-ui.access` and `laranail-artisan-ui.run` abilities are defined (see Install). Both deny until you do.
3. `LARANAIL_ARTISAN_UI_ENABLED=true` is set. While it is false the routes are not registered at all, and the panel answers only in the `local` environment unless you widen `environments`.

### Usage

```bash
# With the `access` and `run` abilities defined (see Install), switch the panel on and check it.
echo "LARANAIL_ARTISAN_UI_ENABLED=true" >> .env
php artisan laranail::artisan-ui.doctor

# Then sign in as an allowed user, open /artisan, pick `about` and press Run.
```

The full walkthrough is in [Getting started](docs/getting-started.md); everything else is in the [documentation index](#documentation).

## <a name="documentation"></a>Documentation

Full documentation is at **[opensource.simtabi.com/documentation/laranail/artisan-ui](https://opensource.simtabi.com/documentation/laranail/artisan-ui/)**.

### Guides

- [Installation](docs/installation.md): requirements, VCS setup, the install command
- [Getting started](docs/getting-started.md): switch the panel on and run a first command
- [Configuration](docs/configuration.md): every key under `laranail.artisan-ui`
- [Architecture](docs/architecture.md): the headless core, the web module, and why they are split
- [Security](docs/security.md): the threat model and every check between a request and a run
- [Release](docs/release.md): how versions are cut and consumed

### Reference

- [Discovery](docs/tools/discovery.md) · [Command policy](docs/tools/command-policy.md) · [Risk levels](docs/tools/risk-levels.md)
- [Authorization](docs/tools/authorization.md) · [Execution](docs/tools/execution.md) · [Output](docs/tools/output.md)
- [Quick actions](docs/tools/quick-actions.md) · [Audit and history](docs/tools/audit-history.md) · [Events and hooks](docs/tools/events.md)
- [Web UI](docs/tools/web-ui.md) · [Commands](docs/tools/commands.md) · [Extending](docs/tools/extending.md)

### Recipes

- [Grant access to admins](docs/recipes/grant-access-to-admins.md) · [Allow only specific commands](docs/recipes/allow-only-specific-commands.md) · [Scope commands per user](docs/recipes/scope-commands-per-user.md)
- [Enable run history](docs/recipes/enable-run-history.md) · [Restrict by IP](docs/recipes/restrict-by-ip.md) · [Keep the panel up during maintenance](docs/recipes/keep-the-panel-up-during-maintenance.md)
- [Add a quick action](docs/recipes/add-a-quick-action.md) · [Custom output decorator](docs/recipes/custom-output-decorator.md) · [Publish and theme the views](docs/recipes/publish-and-theme-views.md)
- [Test with the fake](docs/recipes/test-with-the-fake.md) · [Add a browser hook](docs/recipes/add-a-browser-hook.md) · [Migrate from lorisleiva/artisan-ui](docs/recipes/migrate-from-lorisleiva-artisan-ui.md)

## Contributing & security

Issues and PRs are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately per [SECURITY.md](SECURITY.md) (security@simtabi.com). Participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC, retaining the notices of the three projects it derives from. See [LICENSE](LICENSE).
