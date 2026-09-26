# Getting started

Switch the panel on, decide who may use it, and run a first command, in three deliberate steps.

## Why three steps

Every default fails closed. The panel is off, it answers only in the `local` environment, and every Gate ability denies until the application defines it. Turning it on is a decision made in three places, so no single mistake exposes it.

## 1. Enable it

```dotenv
LARANAIL_ARTISAN_UI_ENABLED=true
```

While this is false the routes are not registered at all. The panel lives at `/artisan` unless you change `path`.

## 2. Decide who may use it

Define the abilities in a service provider. Nobody can open the panel until `access` allows them, and nobody can run a command until `run` does:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

public function boot(): void
{
    Gate::define(Ability::Access->value, fn (User $user): bool => $user->isAdmin());

    Gate::define(Ability::Run->value, fn (User $user, CommandDefinition $command, array $input): bool
        => $user->isAdmin());

    // Optional: the History screen and the environment panel have their own abilities.
    Gate::define(Ability::ViewHistory->value, fn (User $user): bool => $user->isAdmin());
    Gate::define(Ability::ViewEnvironment->value, fn (User $user): bool => $user->isAdmin());
}
```

`Ability::Access->value` is the string `laranail-artisan-ui.access`; either spelling works. See [Authorization](tools/authorization.md) for what each ability receives.

## 3. Check the setup

```bash
php artisan laranail::artisan-ui.doctor
```

It reports whether the panel is enabled, where it is allowed, whether the abilities are still the deny-all defaults, whether the guard keeps a session, and whether `down` would lock the panel out. See [Commands](tools/commands.md).

## Run a command

1. Sign in to the application as a user the `access` ability allows.
2. Open `/artisan`. Commands are grouped by namespace; the search box filters by name and description.
3. Open `about`, leave the fields empty, and press **Run**.

The output appears below the form with its colours intact, followed by the status, exit code and duration. A guest is redirected to the application's `login` route when one exists, and answered `401` otherwise.

## What the panel will not let you do

| You try to | The panel |
|---|---|
| open `serve`, `tinker`, `queue:work` or another long-running command | does not list it and answers `404` (it is [forbidden](tools/risk-levels.md)) |
| run `make:model` outside `local` | refuses with `403` (writes files) |
| run `migrate` or `db:wipe` | asks for the command name typed back and a fresh password confirmation |
| set `--env` or another global option | rejects the input with `422` |
| run the same command twice at once | answers `409` while the first run holds the lock |

## Next steps

- [Configuration](configuration.md): every key, with its default.
- [Allow only specific commands](recipes/allow-only-specific-commands.md).
- [Enable run history](recipes/enable-run-history.md).
- [Security](security.md): read this before enabling the panel anywhere but `local`.

---

[← Docs index](../README.md#documentation)
