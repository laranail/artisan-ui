# Security

The threat model for a web page that runs Artisan commands on the server, and every check between a request and a run, in the order they apply.

## Threat model

A panel that runs Artisan is remote code execution by design; the only question is who gets to use it. The package assumes:

- **Anyone can send a request.** Routes, command names and parameters are guessable. A command that is not listed must be as unreachable by URL as by clicking.
- **A signed-in user is not automatically an operator.** The application decides who may open the panel and, separately, who may run which command with which input.
- **Configuration is edited by people in a hurry.** Removing a middleware, emptying a list or mistyping a value must never open the panel.
- **Commands print things.** Output can contain secrets and markup, and must reach neither a log nor a browser as either.
- **Operators make mistakes.** A destructive command needs deliberate confirmation; two operators must not migrate at once.

What the package does **not** defend against: a user the application's own Gate allows to run a command that is itself harmful. Classification is by name, and a custom command can do anything. That is why the `run` ability is asked for every command, whatever its risk class.

## Who can reach it

With the defaults, nobody:

| Default | Effect |
|---|---|
| `enabled = false` | No route is registered. |
| `environments = ['local']` | Only the `local` environment answers, independently of the Gate. |
| every ability deny-all | Nobody passes `access` or `run` until the application defines them. |
| `require --dev` recommended | The provider does not exist in a production install at all. |

Opening it is a deliberate act in three places. `laranail::artisan-ui.doctor` fails when `production` or `*` is in `environments`, and warns while the abilities are still the defaults. If the panel genuinely has to exist on a deployed environment, combine a narrow `access` ability with `allowed_ips` and a dedicated `domain`.

## The checks, in order

Every check must pass; each failure stops the request before the next check runs.

| # | Check | Where | On failure |
|---|---|---|---|
| 1 | Panel enabled | route registration: while disabled no route exists | `404` |
| 2 | Session, cookies, CSRF | the configured middleware (`web`) | `419` |
| 3 | Panel still enabled (covers a stale route cache) | `PanelAccess` | `404`, not logged |
| 4 | A user on the configured guard | `PanelAccess` | redirect to `login`, or `401` |
| 5 | Environment allowlist | `PanelAccess` | `403` |
| 6 | IP allowlist | `PanelAccess` (`IpUtils::checkIp`; a malformed entry denies) | `403` |
| 7 | `laranail-artisan-ui.access` ability | `PanelAccess` | `403` |
| 8 | Rate limit, per user | `throttle:laranail-artisan-ui` on the run route | `429` |
| 9 | Command listed (allow/deny, not hidden, not forbidden) | `CommandRegistry` | `404` |
| 10 | Input matches the command's definition | `InputValidator` | `422` |
| 11 | Writes-files command in an allowed environment | `CommandAuthorizer` | `403` |
| 12 | `laranail-artisan-ui.run` ability, given the command and input | `CommandAuthorizer` | `403` |
| 13 | Destructive: command name typed back | `DestructiveConfirmation` | `422` |
| 14 | Destructive: password confirmed within the timeout | `DestructiveConfirmation` | `423` |
| 15 | Not forbidden (second line of defence) | `CommandExecutor` | `404` |
| 16 | Lock free | `CommandExecutor` | `409` |

Checks 3 to 7 and the security headers are appended by the package after the configured middleware, so no configuration can remove them. Denials at 5, 6, 7, 11 and 12 are logged at warning on `log_channel` and dispatched as `AccessDenied`, with the reason; the response body never says why. See [Authorization](tools/authorization.md).

The run route accepts `POST` only; a `GET` answers `405`.

## Input

- Only keys the command defines are accepted; anything else is a field error, not an exception from deep inside Symfony.
- Symfony's global options (`--env`, `--verbose`, `--quiet`, `--silent`, `--ansi`, `--no-ansi`, `--no-interaction`, `--help`, `--version`) are removed from the form and refused by the validator. `--env` in particular would switch the environment the command runs in.
- Values must be text, are trimmed, and are bounded by `limits.max_value_length` and `limits.max_array_items`.
- Every run gets `--no-interaction`, so a command that prompts answers its default instead of holding a worker.

## Headers and CSP

`SecurityHeaders` is applied to every panel response, including denials:

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'` |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `same-origin` |
| `Cache-Control` | `no-store, private` |
| `X-Robots-Tag` | `noindex, nofollow` |

No inline script and no `eval` are allowed, and the client needs neither. When `app.asset_url` points at another origin, that origin is added to `script-src`, `style-src` and `font-src`; it is read from configuration, never from the request's `Host` header. Framing is refused, so the run button cannot be clickjacked.

The asset route serves exactly two filenames from a fixed map, so it has no path-traversal surface.

## CSRF and expired sessions

The run and password-confirmation endpoints are CSRF-protected by the `web` group. A page left open past the session lifetime gets `419`. The client then fetches a fresh token from `GET {path}/csrf-token` (behind the same access checks) and tells the operator to run the command again. It **never replays the POST on its own**, because a run is not idempotent.

## Output and redaction

Output reaches the browser as text segments rendered with `textContent`, never as HTML; see [Output](tools/output.md). Before anything is shown, recorded or passed to a listener, `SecretRedactor` masks:

1. the value of every environment variable whose name matches `redaction.keys`, wherever it appears (with and without a `base64:` prefix);
2. `NAME=value` and `NAME: value` lines whose name matches;
3. the password in `scheme://user:password@host` URLs.

Recorded input is masked by key: an option named `--password` is stored masked whatever its value.

> Redaction is best effort. It recognises known values and common shapes; it cannot recognise a secret a command prints in a shape it does not know, or one shorter than `redaction.min_scrub_length`. A command that prints secrets should be **denied**, not relied on to be masked.

`config:show` is forbidden by default for exactly this reason: it prints configuration values, credentials included, as a dotted table the redactor cannot reliably recognise. An application can re-allow it with `risk.safe`, which is checked first; do so knowingly.

Exceptions never reach the browser as text. A command that throws is described by its run id only, in every environment. The one exception is Symfony's console input errors ("Not enough arguments"), which are validation messages about what the operator typed and are shown, redacted.

## Destructive commands

A destructive command needs both:

- **its name typed back**, checked server-side; this cannot be switched off;
- **a password confirmation** within `confirmation.password_timeout`, checked against the guard's own user provider (`validateCredentials`), throttled to five attempts a minute per user. It writes the standard `auth.password_confirmed_at` session key. `confirmation.require_password = false` drops this step.

`down` is destructive because running it from the panel locks the panel out too, including for whoever then needs to run `up`. Exempt the panel's path from maintenance mode or always use `down --secret`; see [Keep the panel up during maintenance](recipes/keep-the-panel-up-during-maintenance.md). `key:generate` is destructive because it rotates `APP_KEY`, signing everyone out and making previously encrypted data unreadable.

## Rate limits

| Limiter | Applies to | Limit |
|---|---|---|
| `laranail-artisan-ui` | `POST .../commands/{command}/run` | `rate_limit.per_minute` (default 30) per user |
| `laranail-artisan-ui-confirm` | `POST .../confirm-password` | 5 per minute per user |

## Locking

Each command takes a cache lock for the length of the run (`limits.lock_seconds`), so two operators cannot run `migrate` at once. All destructive commands share **one** lock, so `migrate:fresh` cannot interleave with `db:wipe`. A second run while the lock is held gets `409`. A cache store without lock support runs unlocked, logs a warning on each run, and doctor warns.

## Audit

Every run is recorded when it starts and when it ends, with the actor, IP, command, redacted input, risk, status, exit code and duration; see [Audit and history](tools/audit-history.md). Recording starts before the command runs, so a run that never finishes still leaves a record. A failing audit store never stops a run and never hides one: the record falls back to the log and the store is reported as degraded.

Point `audit.connection` at a connection `migrate:fresh` and `db:wipe` do not touch if those commands are run from the panel; the fallback keeps the record, but a separate connection keeps the history.

## Long-lived workers

Commands run in the same PHP process as the request. Under Octane, a command that changes configuration or caches can leave that worker in a different state from its siblings; doctor warns when Octane is installed.

## Reporting a vulnerability

Report security issues privately to **security@simtabi.com**. Do not open a public issue. See `SECURITY.md` in the repository for what to include and response times.

---

[← Docs index](../README.md#documentation)
