# Migrate from lorisleiva/artisan-ui

Move an application from `lorisleiva/artisan-ui` to `laranail/artisan-ui`: new package and namespace, the same `path` and `domain`, and a Gate in place of the auth callback.

## Swap the package

```bash
composer remove lorisleiva/artisan-ui
```

Add the VCS repositories from [Installation](../installation.md), then:

```bash
composer require --dev laranail/artisan-ui
php artisan laranail::artisan-ui.install
```

Delete the old published files: `config/artisan-ui.php`, `public/vendor/artisan-ui` (the old stylesheet; the new panel serves its own through a hashed route) and any `resources/views/vendor/artisan-ui`.

## Map the configuration

| `lorisleiva/artisan-ui` | `laranail/artisan-ui` |
|---|---|
| `config/artisan-ui.php`, `config('artisan-ui.*')` | `config/laranail/artisan-ui.php`, `config('laranail.artisan-ui.*')` |
| `domain` | `domain` (unchanged; also `LARANAIL_ARTISAN_UI_DOMAIN`) |
| `path` = `'artisan'` | `path` = `'artisan'` (unchanged; also `LARANAIL_ARTISAN_UI_PATH`) |
| `middleware` = `['web', AuthorizeArtisanUI::class]` | `middleware` = `['web']`. Drop `AuthorizeArtisanUI`: the access check and security headers are appended automatically and cannot be removed. |
| always on | `enabled` = `false`; set `LARANAIL_ARTISAN_UI_ENABLED=true` |
| `local` by default through the auth callback | `environments` = `['local']`, checked **in addition** to the Gate |
| `php artisan artisan-ui:install` | `php artisan laranail::artisan-ui.install` |
| `Lorisleiva\ArtisanUI\...` | `Simtabi\Laranail\ArtisanUI\...`; facade `Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI`, with no global alias |

## Replace `ArtisanUI::auth()`

The callback received the request; the abilities receive the signed-in user, and running a command is decided separately, per command:

```php
// Before
ArtisanUI::auth(function ($request) {
    return app()->environment('local') || optional($request->user())->isAdmin();
});
```

```php
// After
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('laranail-artisan-ui.access', fn (User $user): bool => $user->isAdmin());
Gate::define('laranail-artisan-ui.run', fn (User $user, $command, array $input): bool => $user->isAdmin());
```

There is no "anyone in local" mode: a user must be signed in, and the environment check is the separate `environments` list. See [Authorization](../tools/authorization.md).

## Update links

| Before | After |
|---|---|
| `GET /artisan/{name}`, route `artisan-ui.detail` | `GET /artisan/commands/{name}`, route `laranail-artisan-ui.detail` |
| `POST /artisan/{name}/execution`, route `artisan-ui.execution` | `POST /artisan/commands/{name}/run`, route `laranail-artisan-ui.execute` |
| `GET /artisan`, route `artisan-ui.home` | `GET /artisan`, route `laranail-artisan-ui.home` |

## What is now refused by default

- Everyone, until the `access` and `run` abilities are defined.
- Long-running and interactive commands (`serve`, `tinker`, `queue:work`, `horizon`, ...) and `config:show`: never listed, `404`.
- Hidden commands, unless `commands.include_hidden` is true.
- Global options such as `--env` and `--verbose`, and any key the command does not define: `422`.
- Writes-files commands (`make:*`, `vendor:publish`, ...) outside `local`: `403`.
- Destructive commands (`migrate*`, `db:wipe`, `db:seed`, `key:generate`, `down`, ...) without the command name typed back and a fresh password confirmation.
- A second run of a command that is still running: `409`.
- Prompts: every run is non-interactive, so a command that asks a question gets its default answer.

Check what the panel now exposes with `php artisan laranail::artisan-ui.policy`. See [Risk levels](../tools/risk-levels.md).

---

[← Docs index](../../README.md#documentation)
