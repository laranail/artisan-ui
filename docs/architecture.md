# Architecture

A headless core that decides and runs, a web module that carries requests to it, and the reasons the two are kept apart.

## Two layers

| Layer | Namespace | Knows about | Never touches |
|---|---|---|---|
| Core | `Simtabi\Laranail\ArtisanUI\Core` | commands, policy, risk, validation, execution, output, audit, events, presets | `Illuminate\Http`, routing, views, any `Modules\*` class |
| Web module | `Simtabi\Laranail\ArtisanUI\Modules\WebUI` | routes, middleware, controllers, sessions, Blade, assets | nothing the core does not expose |

The boundary is enforced, not described: `tests/Arch/BoundaryTest.php` fails if anything in `Core` uses the HTTP, routing or view layers, or reaches into `Modules`.

Around them sit the pieces both layers share:

| Piece | Where | Role |
|---|---|---|
| `ArtisanUIServiceProvider` | `src/Providers` | Configures the package through `laranail/package-tools`, binds the core services, defines the deny-all abilities, registers the web module. |
| `WebUIServiceProvider` | `src/Modules/WebUI/Providers` | Builds the route group by hand so the guards are appended to, never replaced by, the configured middleware. |
| Enums | `src/Enums` | `Ability`, `CommandRisk`, `RunStatus`, `AuditDriver`, `AssetMode`, all `laranail/enumerator` enums. |
| `ArtisanUI` / facade | `src/ArtisanUI.php`, `src/Facades` | The application-facing API: read commands and risk, register quick actions and decorators, install the fake. |
| Doctor checks | `src/Doctor/Checks.php` | Rendered by `laranail::artisan-ui.doctor` and by `laranail::package-tools.doctor`. |
| `FakeRunner` | `src/Testing` | A `CommandRunner` that records instead of running. |

### Core components

| Component | Responsibility |
|---|---|
| `Discovery\CommandRegistry` | The commands the panel exposes, already filtered by policy. Scoped per request. |
| `Discovery\CommandDefinition` | An immutable description of one command, with no reference back to the command instance. |
| `Policy\CommandPolicy` | Allow and deny lists; hidden commands. |
| `Policy\RiskClassifier` | Name → `CommandRisk`, from the package defaults merged with configuration. |
| `Policy\PanelAccess` | May this user open the panel: enabled, environment, IP, `access` ability. |
| `Policy\CommandAuthorizer` | May this user run this command with this input: policy, forbidden, writes-files environment, `run` ability. |
| `Validation\InputValidator` | Checks request input against the command's own definition. |
| `Execution\CommandExecutor` | Lock, audit, events, decoration, redaction around a runner. |
| `Execution\SyncRunner` | The default `CommandRunner`: runs in-process through the console kernel. |
| `Output\DecoratorRegistry`, `SecretRedactor`, `AnsiFormatter` | Rewrite, scrub, and segment output. |
| `Audit\LogRecorder`, `DatabaseRecorder`, `NullRecorder` | The three `RunRecorder` implementations. |
| `Presets\PresetCatalog`, `MigrationExistsGuard` | Quick-action groups and the duplicate-migration guard. |
| `Environment\EnvironmentInfo` | PHP, Laravel, database and driver versions for the environment panel. |

## How a run flows

One endpoint runs anything: `POST {path}/commands/{command}/run`. Every step below must pass before the next happens; a failure stops the chain.

1. **Configured middleware.** By default `web`: session, cookies, CSRF. An expired token answers `419`.
2. **`SecurityHeaders`** wraps the response, denials included.
3. **`EnsureArtisanUIAccess`** asks `PanelAccess`: disabled → `404`; no signed-in user on the configured guard → redirect to `login` or `401`; environment, IP or `access` ability refused → `403`. It then makes the configured guard the default for the rest of the request.
4. **Rate limit** (`throttle:laranail-artisan-ui`), per user → `429`.
5. **Registry lookup.** The command must be listed: not denied by policy, not hidden, not forbidden → otherwise `404`.
6. **Validation.** Arguments and options are checked against the command's definition → `422` with field errors.
7. **Authorization.** `CommandAuthorizer` re-checks policy and forbidden (`404`), refuses writes-files commands outside their environments (`403`), and asks the `run` ability with the command and the validated input (`403`). Denials are logged and dispatched as `AccessDenied`.
8. **Destructive confirmation.** The command name typed back (`422`), then a password confirmation within the timeout (`423`).
9. **Execution.** `CommandExecutor` refuses a forbidden command once more, takes the lock (`409` if held), generates the run id, records the start, dispatches `CommandExecuting`, calls the runner.
10. **After the runner.** An exception is reported; truncation is logged; output is decorated, then redacted; the finish is recorded; `CommandExecuted` or `CommandFailed` is dispatched; the lock is released.
11. **Response.** `RunPresenter` turns the result into JSON: status, exit code, duration, and output as ANSI segments. An exception is described by run id only.

Quick actions and history reruns never run anything: they link to the command form with values pre-filled, and the form submits to the same endpoint.

## Failure handling

Every failure is classified, and the classification decides what the operator sees, what is reported, and whether the package is marked degraded. There is no branch on `app.debug` or on the environment anywhere in this.

| Failure | Classification | What happens |
|---|---|---|
| The command throws | Reported and recorded | Wrapped in `CommandRunFailed` (run id, command, risk, decision `recorded-errored`, cause kept as `previous`) and sent to the exception handler. The run is recorded as `errored`. The browser gets a generic message with the run id. |
| The command exits non-zero | Normal outcome | Recorded as `failed`; `CommandFailed` is dispatched. |
| The audit store fails (table missing, write error) | Degradable | Through package-tools `FailurePolicy::handle(..., BootCriticality::Degradable)`: reported, recorded in `BootReport` as `laranail/artisan-ui:audit.database`, surfaced by `boot:health`. The run is still recorded, in the log. |
| An output decorator throws | Degradable, fail closed | The output is withheld rather than shown undecorated, because a decorator may exist to hide something. Reported and recorded in `BootReport` as `laranail/artisan-ui:output.decorate`. |
| Output exceeded the cap | Tolerated anomaly | Truncated, flagged in the response, logged at warning (`tolerated anomaly [laranail/artisan-ui:run.output]`). |
| The cache store cannot lock | Tolerated anomaly | The run proceeds unlocked, logged at warning (`...run.lock`). Doctor warns. |
| The run row vanished before the finish (after `migrate:fresh`) | Tolerated anomaly | Logged at warning (`...audit.database`); the start and finish are written to the log instead. |
| Password confirmation missing or expired | Tolerated anomaly | `423` to the client; logged at warning (`...confirm.password`). |
| The log channel itself fails | Logging substrate | Falls back to PHP's `error_log()`; it cannot report through itself. |
| Reporting itself fails | Guarded | `error_log()` last resort; a broken monitoring integration never turns a handled failure into a crash. |

A clean run leaves `BootReport` healthy; the suite asserts it.

## Why a module namespace?

The web panel is one way to reach the core, not the only one. An API, a scheduler screen, a terminal UI or an MCP server would each need the same answers: which commands exist, may this user run this one, run it safely, record it. Keeping those answers in a headless `Core` and the web in `Modules\WebUI` means a second module is a new directory with its own provider, consuming `Core` exactly as the web module does, with no refactor of the security path. `PanelAccess` is deliberately free of the request object for this reason.

## Why no built-in login?

Every application already has a way to sign users in, and a second one would be a second password store to secure. The forks this package draws on show the risk: one hardcoded a username and password, the other had no authentication at all. The panel instead trusts whichever user the configured session guard already holds, and redirects a guest to the application's own `login` route.

## Why Gate abilities instead of an auth callback?

Upstream's `ArtisanUI::auth(fn ($request) => ...)` answered one question, once per request, about the request. That is too coarse for a tool that can drop a database. Gate abilities answer per user **and** per command: `run` receives the `CommandDefinition` and the validated input, so an application can let support staff clear caches while only admins migrate. They also compose with everything the application already has (policies, `Gate::before`, roles packages), and they are testable with the framework's own tools. The package defines each ability as deny-all only when the application has not defined it (`Gate::has()` first), so an application definition always wins regardless of provider order.

## Why framework-free JavaScript, and no Alpine?

The panel runs under a Content Security Policy that allows scripts only from its own origin, with no inline code and no `eval`. Alpine's expressions need either `unsafe-eval` or its restricted CSP build, and rendering server output through `x-html` would reintroduce exactly the markup-injection surface the panel avoids. The client is instead a small, framework-free ES module: a composition root (`createArtisanUi`), a headless transport, DOM controllers that progressively enhance server-rendered Blade, bubbling vendor-prefixed events, and ordered hooks. It has no runtime dependencies, so nothing third-party is bundled or loaded from a CDN.

## Why output travels as segments, not HTML?

Command output can contain anything, including markup a command echoes from user data. `AnsiFormatter` therefore never produces HTML: it turns ANSI colour codes into `{text, classes}` pairs whose class names come from a fixed table. The browser sets each segment with `textContent` and accepts a class list only in the `lau-*` shape; the History view does the same with Blade's escaping `{{ }}`. Nothing in a command's output can become an element in the panel, whatever the command prints.

## Lineage

`laranail/artisan-ui` adopts [`lorisleiva/artisan-ui`](https://github.com/lorisleiva/artisan-ui) (MIT), whose last commit was in June 2021 and which supported Laravel 8 only. It folds in the features of two unrelated MIT projects, [`pabloleone/artisan-ui`](https://github.com/pabloleone/artisan-ui) and [`dev-arindam-roy/artisan-ui`](https://github.com/dev-arindam-roy/artisan-ui). Their copyright notices are kept in `LICENSE`. Every upstream issue and pull request was applied by intent, since none could be merged as-is:

| Source | Item | How it was applied |
|---|---|---|
| lorisleiva #1 | PHP 8 requirement question | No change needed; the floor is now `^8.4.1 \|\| ^8.5`. |
| lorisleiva #2 (merged) | Open the arguments panel when a command has required arguments | Kept, with its bug fixed: upstream rendered `{ open:  }` for false, which is invalid JavaScript. A regression test covers it. |
| lorisleiva #3 | Remember the open group in the URL | Implemented: the open group is mirrored into `#group-<namespace>`. |
| lorisleiva #6, #7, #10 | Laravel 9, 10 and 11 to 13 support | Superseded by the Laravel 13 rewrite. Kept: #7's horizontally scrolling output, and #10's non-interactive runs, as `--no-interaction` on every call. |
| lorisleiva #9 | Allow and deny lists | Reimplemented in `CommandPolicy` with `Str::is()`, fixing its `str_starts_with` matching so `migrate:*` also covers `migrate`. Its unused `switch_size` key was dropped. |
| pabloleone branch `pabloleone-patch-1` | Unmerged before/after execute events | Ported as `CommandExecuting`, `CommandExecuted` and `CommandFailed`. |
| pabloleone #2 | Running a command twice sends invalid arguments | Covered by a rerun regression test; execution goes through the console kernel with fresh input every time. |
| pabloleone PR #1 | Lint workflow | Replaced by the laranail CI. |
| dev-arindam-roy PR #1 | Add a licence | Its notice is retained in `LICENSE`. |

Features carried over from the two forks, all on the authenticated-user and Gate model:

| Feature | From | Now |
|---|---|---|
| Deny list, server-side input validation | pabloleone | `CommandPolicy`, `InputValidator` (also refuses global options such as `--env`) |
| Output decorators, events, themes, translations | pabloleone | `DecoratorRegistry`, `Core\Events`, `theme` with per-view fallback, `laranail/artisan-ui::` translations |
| Search, quick actions, duplicate-migration guard, environment panel | dev-arindam-roy | client-side filter, `PresetCatalog` (pre-fill only), `MigrationExistsGuard`, `EnvironmentInfo` behind its own ability |

Deliberately not carried over: hardcoded credentials, a run endpoint reachable by unauthenticated `GET`, a panel that is on by default with no authentication, and scripts loaded unpinned from a CDN.

---

[← Docs index](../README.md#documentation)
