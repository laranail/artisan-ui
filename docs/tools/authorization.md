# Authorization

Four Gate abilities, the cases of `Simtabi\Laranail\ArtisanUI\Enums\Ability`, decide who may open the panel, run a command, read history and see the environment; the application defines them, and until it does each one denies.

## The abilities

| Case | Ability name | Receives | Checked |
|---|---|---|---|
| `Ability::Access` | `laranail-artisan-ui.access` | the user | on every panel request, by `EnsureArtisanUIAccess` |
| `Ability::Run` | `laranail-artisan-ui.run` | the user, the `CommandDefinition`, the validated input array | on every run, after validation and before any confirmation |
| `Ability::ViewHistory` | `laranail-artisan-ui.view-history` | the user | on the History screen (and to show its link) |
| `Ability::ViewEnvironment` | `laranail-artisan-ui.view-environment` | the user | to show the environment panel on the home screen |

`Ability::Run->value` and the string `'laranail-artisan-ui.run'` are the same thing; use whichever reads better.

The input passed to `run` is the validated, **unredacted** input:

```php
[
    'arguments' => ['name' => 'users'],                 // array<string, string|list<string>>
    'options'   => ['force' => true, 'step' => '1'],     // array<string, bool|string|list<string>|null>
]
```

Only keys the command defines appear, global options never do, and an option given without a value is `null`.

## Defining them

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

Gate::define(Ability::Access->value, fn (User $user): bool => $user->isAdmin());

Gate::define(Ability::Run->value, function (User $user, CommandDefinition $command, array $input): bool {
    return $user->isAdmin()
        || ($user->isSupport() && str_ends_with($command->name, ':clear'));
});

Gate::define(Ability::ViewHistory->value, fn (User $user): bool => $user->isAdmin());
Gate::define(Ability::ViewEnvironment->value, fn (User $user): bool => $user->isAdmin());
```

Anything the Gate supports works: a policy class method, a closure, or a `Gate::before()` callback. Note that a `Gate::before()` returning `true` for super-admins also grants every ability here, including `run` for every listed command.

## Deny by default

At boot, `Simtabi\Laranail\ArtisanUI\Core\Policy\DefaultAbilities` defines each ability as `fn () => false`, but **only when `Gate::has()` says the application has not defined it already**. An application definition therefore wins whatever order the providers boot in: defined earlier, the package skips it; defined later, `Gate::define()` replaces the package's closure.

`laranail::artisan-ui.doctor` reads the Gate back and warns while `access` or `run` is still the package default ("Nobody can use the panel yet"), so the first sign of a missing definition is not a `403`.

## Panel access

`Simtabi\Laranail\ArtisanUI\Core\Policy\PanelAccess` answers "may this user open the panel at all", independent of the transport. Its checks run cheapest first and all must pass:

| Check | Refusal | Status |
|---|---|---|
| `enabled` is `true` | `disabled` | `404` |
| a user is signed in on `guard` | `unauthenticated` | redirect to the `login` route, or `401` (JSON requests always `401`) |
| the environment is in `environments` | `environment` | `403` |
| the client IP is in `allowed_ips` (when set) | `ip` | `403` |
| the `access` ability allows the user | `gate` | `403` |

Once allowed, the middleware makes the configured guard the default for the rest of the request, so `$request->user()` and every later Gate check see the same user.

The middleware is also registered as the `laranail-artisan-ui` alias, so an application can put the same check in front of its own routes:

```php
Route::get('/ops', OpsController::class)->middleware('laranail-artisan-ui');
```

## Per-command scoping

`Simtabi\Laranail\ArtisanUI\Core\Policy\CommandAuthorizer` decides whether this user may run this command with this input:

| Check | Refusal reason | Status |
|---|---|---|
| the [command policy](command-policy.md) permits it | `policy` | `404` |
| its [risk class](risk-levels.md) is not forbidden | `forbidden` | `404` |
| a writes-files command runs in `risk.writes_files_environments` | `writes_files_environment` | `403` |
| the `run` ability allows it for the command and input | `gate` | `403` |

The first two repeat what the registry already enforced; they are there so no future transport can skip them. The last is where applications scope: by user, by command name, by namespace, by risk (`ArtisanUI::risk($command->name)`), or by input value.

Destructive confirmation is not an authorization check: it depends on the session, so the web module applies it after the authorizer.

## Denials

Every denial except `disabled` and `unauthenticated` is:

- logged at warning on `log_channel` as `laranail/artisan-ui: access denied`, with the reason, status, IP, path, user id and command;
- dispatched as `Simtabi\Laranail\ArtisanUI\Core\Events\AccessDenied` (see [Events and hooks](events.md));
- answered with the status and a generic sentence: browsers get the panel's own denial page, JSON clients the bare status. The person refused never learns the reason.


## Why input is validated before the Run check

The Run ability receives the **validated** input, so the application's callback sees clean, typed values instead of raw request data. Validation therefore runs first, and a user who has Access but not Run can get a 422 before the 403. That 422 only repeats what the command's page already shows that user: which arguments exist and which are required. Nothing runs, and nothing about other users or the server is revealed.

---

[← Docs index](../../README.md#documentation)
