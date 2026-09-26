# Events and hooks

Four PHP events on the server and six DOM events plus two hooks in the browser; events observe, hooks can change or veto.

## PHP events

All four live in `Simtabi\Laranail\ArtisanUI\Core\Events` and are dispatched through the application's event dispatcher.

| Event | When | Payload |
|---|---|---|
| `CommandExecuting` | after the lock is taken and the start recorded, before the runner is called | `context` |
| `CommandExecuted` | the command exited 0 | `context`, `result`, `output` |
| `CommandFailed` | the command exited non-zero, or threw (`$result->exception` is set) | `context`, `result`, `output` |
| `AccessDenied` | the panel or a run was refused | `reason`, `ip`, `path`, `userId`, `command` |

`CommandExecuting` and `CommandExecuted` are the pabloleone fork's unmerged before/after events, ported.

### `RunContext`

| Property / method | Meaning |
|---|---|
| `runId` | The ULID shared with the audit record and the response. |
| `command` | The `CommandDefinition`. |
| `input` | The `ValidatedInput`, **unredacted**. |
| `redactedInput` | `['arguments' => ..., 'options' => ...]` with secret-named keys masked. Use this for anything you store or send. |
| `risk` | The `CommandRisk`. |
| `actor`, `actorId()`, `actorType()` | The signed-in user, its identifier as a string, its class. |
| `ip` | The client address. |
| `startedAt` | `CarbonImmutable`. |

`result` is the `RunResult` (status, exit code, duration, truncated, exception). `output` is the decorated, **redacted** output; the raw output is never given to listeners.

### `AccessDenied`

Scalars only, so a listener can be queued. `reason` is one of:

| Reason | Raised by |
|---|---|
| `environment`, `ip`, `gate` | opening the panel (`gate` = the `access` ability) |
| `gate` | the History screen (the `view-history` ability) |
| `writes_files_environment`, `gate` | a run (`gate` = the `run` ability) |
| `policy`, `forbidden` | a run, as a second line of defence behind the registry's `404` |

A disabled panel (`404`) and a guest (`401` or redirect) are not dispatched. `command` is set for run refusals.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuted;

Event::listen(function (CommandExecuted $event): void {
    if ($event->context->risk->requiresConfirmation()) {
        Log::channel('ops')->notice('Destructive command ran from the panel', [
            'run_id'  => $event->context->runId,
            'command' => $event->context->command->name,
            'user'    => $event->context->actorId(),
            'input'   => $event->context->redactedInput,
        ]);
    }
});
```

## Browser events

Every event is a bubbling DOM `CustomEvent` named `laranail-artisan-ui:<name>`, so a page can listen with plain delegation on `document` and never needs the client instance.

| Event | Dispatched on | `detail` | Cancelable |
|---|---|---|---|
| `laranail-artisan-ui:ready` | `<body>` | `{ ui }`: the mounted instance | no |
| `laranail-artisan-ui:search` | the command list | `{ query, visible }` | no |
| `laranail-artisan-ui:run:before` | the command form | `{ command, body }`: the payload about to be sent | **yes**: `preventDefault()` stops the run |
| `laranail-artisan-ui:run:finished` | the command form | the run response body, when `success` is true | no |
| `laranail-artisan-ui:run:failed` | the command form | the run response body, when the command failed or errored | no |
| `laranail-artisan-ui:run:refused` | the command form | `{ status, body }` for `422`, `423`, `419`, `409`, `403`, `404`, `0` (network) and anything else | no |

`body` in `run:before` is `{ arguments: {...}, options: {...}, confirm? }`. An aborted request (the page tearing down) dispatches nothing.

```js
document.addEventListener('laranail-artisan-ui:run:finished', (event) => {
  console.info('run', event.detail.run_id, event.detail.status)
})
```

## Browser hooks

Hooks live on the instance, `ui.hooks`, and run in registration order; they may be async.

| Hook | Receives | May |
|---|---|---|
| `beforeRun` | `{ command, body }` | return `false` to veto, a replacement payload (`{ command, body }`) to rewrite what is sent, or `undefined`/`null` to leave it. Anything else is refused as unexpected, and nothing is sent |
| `afterRun` | `{ command, response }`, where `response` is `{ status, body }` | observe; its return value is ignored |

A hook that **throws vetoes** the run rather than being skipped: a hook is often a guard, and a broken guard must not wave the action through. `hooks.add()` returns a function that removes the hook.

`beforeRun` runs before `run:before` is dispatched, and the event carries the payload the hooks produced. `afterRun` runs for every response, refusals included, before the response is rendered. An aborted request (the page tearing down) is not a response: it runs no hook and dispatches no event.

```js
const remove = ui.hooks.add('beforeRun', ({ command, body }) => {
  if (command === 'down' && !body.options.secret) {
    return false   // never take the site down without a bypass secret
  }
})
```

See [Add a browser hook](../recipes/add-a-browser-hook.md) for how to get the instance on the page.

## The notifier seam

A notifier routes panel messages to a toast library. The default does nothing, since the panel already shows every message inline.

```js
/** @type {{ notify(level: 'info'|'success'|'warning'|'error', event: string, detail?: object): void }} */
const notifier = {
  notify(level, event, detail) { myToasts.show(level, event) },
}
```

It is called with `('success' | 'error', 'run', body)` after a completed run and `('warning', 'run:refused', { status })` after a refusal. It is passed to `createArtisanUi({ notifier })`; see [Extending](extending.md).

---

[← Docs index](../../README.md#documentation)
