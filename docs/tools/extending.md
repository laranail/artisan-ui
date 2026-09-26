# Extending

The `ArtisanUI` facade (`Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI`) is the application-facing API, backed by `Simtabi\Laranail\ArtisanUI\ArtisanUI` and resolved from the container under that class name; below it sit two container seams and a browser composition root.

## The facade

No global alias is registered, because a bare `ArtisanUI` alias is exactly the kind of flat-registry name the family keeps out. Import the class:

```php
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;
```

| Method | Returns | Does |
|---|---|---|
| `commands()` | `array<string, CommandDefinition>` | Every command the panel lists, keyed and sorted by name. |
| `find(string $name)` | `?CommandDefinition` | One listed command, or `null`. |
| `risk(string $name)` | `CommandRisk` | The command's risk class. |
| `quickActions(string $key, string $label, array $actions, ?string $description = null)` | the manager | Add a quick-action group to the home screen. |
| `decorate(string $pattern, string\|OutputDecorator $decorator)` | the manager | Rewrite the output of every command matching the pattern. |
| `fake(?FakeRunner $runner = null)` | `FakeRunner` | Swap the runner for a recording fake. |

Registration methods return the manager, so a provider reads as one chain:

```php
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

public function boot(): void
{
    ArtisanUI::quickActions('deploy', 'Deploy', [
        new QuickAction('Warm caches', 'optimize'),
    ])->decorate('about', \App\ArtisanUI\AboutDecorator::class);
}
```

Quick-action groups and decorators are held by singletons, so register them in a provider's `boot()`, once. Authorization is not configured through the facade; it is the Gate abilities in [Authorization](authorization.md). That is where upstream's `ArtisanUI::auth()` callback now lives.

## Wrapping the runner or the recorder

To decorate a **service** rather than a command's output, use the container's `extend()`. The two seams worth wrapping are `CommandRunner` (runs a command) and `RunRecorder` (writes the audit trail):

```php
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;

final readonly class SlowRunAlert implements CommandRunner
{
    public function __construct(private CommandRunner $inner) {}

    public function run(CommandDefinition $command, ValidatedInput $input): RunResult
    {
        $result = $this->inner->run($command, $input);

        if ($result->durationMs > 30_000) {
            Log::warning('Slow Artisan UI run', ['command' => $command->name, 'ms' => $result->durationMs]);
        }

        return $result;
    }
}

// in a service provider's register()
$this->app->extend(CommandRunner::class, fn (CommandRunner $runner) => new SlowRunAlert($runner));
```

```php
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;

$this->app->extend(RunRecorder::class, fn (RunRecorder $recorder) => new MirroringRecorder($recorder));
```

Extenders run in registration order, each receiving the previous result, so **the last one registered wraps outermost**. A wrapper must keep each contract's promises: a runner reports failure through its `RunResult` rather than throwing, and a recorder never throws.

The recorder is chosen from `audit.driver` with a closed `match` (`log`, `database`, `none`); a different store is a rebind or an extender, not a new driver name.

`ArtisanUI::fake()` installs the fake as a container **instance**. Extenders registered before it do not wrap it; an `extend()` registered after it wraps it immediately.

## `FakeRunner`

`Simtabi\Laranail\ArtisanUI\Testing\FakeRunner` records what it was asked to run and runs nothing. Every check around the runner (access, policy, validation, authorization, confirmation, locking, audit, events, decoration, redaction) still runs for real.

| Method | Does |
|---|---|
| `respondWith(string $command, RunResult\|Closure\|string $response)` | Canned response for one command. A string is successful output; a closure receives `(CommandDefinition, ValidatedInput)` and returns a `RunResult`. Returns the fake. |
| `runs()` | Every recorded run: `['command' => ..., 'input' => ['arguments' => ..., 'options' => ...]]`. |
| `assertRan(string $command, ?Closure $matching = null)` | At least one run of the command, optionally with input the closure accepts. |
| `assertNotRan(string $command)` | No run of the command. |
| `assertNothingRan()` | No run at all. |

A command without a canned response succeeds with empty output. See [Test with the fake](../recipes/test-with-the-fake.md).

## The browser client

The bundled script mounts one instance itself and exposes it as `window.laranailArtisanUi`; add hooks to that instance ([Add a browser hook](../recipes/add-a-browser-hook.md)). `createArtisanUi()` is the composition root it is built from, exported for tests and custom builds. Every collaborator is a parameter with a default:

| Option | Default | Purpose |
|---|---|---|
| `document` | `globalThis.document` | The document to mount on. |
| `window` | `globalThis.window` | For the location hash, history and `matchMedia`. |
| `fetch` | `globalThis.fetch` | The transport; inject a fake in tests. |
| `storage` | `window.localStorage`, or `null` when refused | Where the dark-mode choice is kept. |
| `notifier` | a no-op | Routes panel messages to a toast library; see [Events and hooks](events.md#the-notifier-seam). |
| `hooks` | a new `HookRegistry` | Share a registry across instances, or pre-register hooks. |

```js
import { createArtisanUi } from './vendor/laranail/artisan-ui/resources/js/create.js'

const ui = createArtisanUi({ notifier: myToasts })

ui.hooks.add('beforeRun', ({ command }) => (command === 'down' ? false : undefined))
ui.mount()
```

> `resources/js` is export-ignored, so a Composer dist install carries only the built bundle. Importing the source modules needs a source install (`composer require laranail/artisan-ui --prefer-source`) or a copy of `resources/js` in your own build.

The instance exposes:

| Member | Meaning |
|---|---|
| `hooks` | The `HookRegistry`: `add(name, fn)` returns a remover. |
| `client` | The headless `RunClient`: `run()`, `confirmPassword()`, `refreshToken()`. Every call resolves to `{ status, body }` (status `0` for a network failure, `-1` for a request aborted by `destroy()`) and never rejects; POSTs are never retried. |
| `mount()` | Attaches the controllers to the page and dispatches `laranail-artisan-ui:ready`. Returns the instance. Does nothing on a page without the panel's `<body data-laranail-artisan-ui>`. |
| `destroy()` | Removes every listener and aborts a run in flight. |

Importing the built bundle mounts its own instance, so a custom build should import `create.js`, not the bundle, to avoid mounting twice. Any script on the page must be served from the panel's own origin: the CSP allows no inline or third-party script.

---

[← Docs index](../../README.md#documentation)
