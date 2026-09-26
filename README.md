# laranail/artisan-ui

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

There is no Packagist listing or CI run yet, so the licence badge is the only one whose claim is true; the registry-version, Tests and Static analysis badges arrive with the repository. [Install](#install) covers the VCS route.

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
