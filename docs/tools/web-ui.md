# Web UI

Seven named routes, five views and a framework-free script make up the web module, `Simtabi\Laranail\ArtisanUI\Modules\WebUI`, registered by its own `WebUIServiceProvider`.

## Routes

All panel routes are prefixed with `path` (default `artisan`), bound to `domain` when set, and named `laranail-artisan-ui.*`. They carry the configured `middleware`, then `SecurityHeaders`, then `EnsureArtisanUIAccess`; the last two are appended by the package and cannot be configured away.

| Method | URI | Name | Purpose |
|---|---|---|---|
| `GET` | `/artisan` | `laranail-artisan-ui.home` | Command list, search, quick actions, environment panel |
| `GET` | `/artisan/commands/{command}` | `laranail-artisan-ui.detail` | One command's form; accepts a pre-fill query string |
| `POST` | `/artisan/commands/{command}/run` | `laranail-artisan-ui.execute` | **The only route that runs anything.** Rate-limited per user. |
| `POST` | `/artisan/confirm-password` | `laranail-artisan-ui.confirm-password` | Password re-authentication before a destructive run; 5 attempts a minute |
| `GET` | `/artisan/csrf-token` | `laranail-artisan-ui.csrf-token` | A fresh CSRF token after a `419` |
| `GET` | `/artisan/history` | `laranail-artisan-ui.history` | Recorded runs (database driver and `view-history` only) |
| `GET` | `/vendor/laranail-artisan-ui/{file}` | `laranail-artisan-ui.asset` | The stylesheet and script, in `route` asset mode only |

`{command}` accepts `[A-Za-z0-9:_.-]+`. While `enabled` is false no route is registered. A route cache built while the panel was enabled still carries them, which is why the access middleware checks `enabled` again on every request and answers the framework's plain `404`, never the panel's own page, so a disabled panel is indistinguishable from none.

The asset route has no auth, deliberately: the files are the same public bundle for everyone, and the `403` page needs its styling too. It accepts exactly two filenames.

A refused browser request gets the panel's own denial page (`themes/<theme>/denied.blade.php`), not the framework's error view. The framework views style themselves inline, which the panel's CSP refuses, and the CSP applies to denials too. The page shows the status and a generic sentence, never the reason. JSON clients get the bare status.

## Pages

| Page | View | Shows |
|---|---|---|
| Home | `home` | Commands grouped by namespace in collapsible groups, with a risk badge on every command that is not safe; a search box over name and description; the Quick actions sidebar; the environment panel (with `view-environment`); a History link (with the database driver and `view-history`). |
| Command | `command` | Name, risk badge, description; usage (synopsis and help); the Arguments and Options sections; a risk notice; for destructive commands, the typed-confirmation field; **Run**; the output panel; the password dialog. |
| History | `history` | Filter by command and status; runs newest first, each expandable to run id, error and output; **Rerun**. |
| Layout | `layout` | Header, navigation (History appears on every page when the database driver is on and `view-history` allows it), environment name, dark-mode toggle, and the asset tags. |
| Denied | `denied` | The status and a generic sentence, never the reason. Standalone: no layout and no script. Rendered for browser denials; JSON clients get the bare status. |

Two partials, `partials.field` (one argument or option) and `partials.risk-badge`, complete the set.

## Form controls

`partials.field` picks a control from the input's definition:

| Definition | Control | Sent as |
|---|---|---|
| Argument, or option taking a value | text input | `arguments[name]` / `options[name]` |
| List argument or option (`=*`) | one text input per value, with Add and Remove | `…[name][]` |
| Option whose value is optional | text input, plus "Send `--name` without a value" | the typed value, or `true` when only the box is ticked |
| Flag (`--force`) | checkbox | `options[name]=1` |
| Negatable flag (`--ansi` / `--no-ansi`) | three-way select: Not set, `--name`, `--no-name` | `1` or `no` (becomes `--no-name`) |

Every list row is labelled for assistive technology, removing a row keeps focus in the list, and a server-side error inside a collapsed section opens it before focusing the field.

## Dark mode

Dark follows the operating system preference by default, in CSS alone, so the first paint and the script-free denied page are right. The header toggle stores an explicit choice (`laranail-artisan-ui:theme` in local storage) that overrides the system.

The Arguments section opens automatically when the command has a required argument or the form is pre-filled with one; Options opens when it is pre-filled with an option. Flags are checkboxes, list fields get add and remove buttons, and every field shows its description and its default as a placeholder.

The environment panel lists PHP, Laravel, environment, debug mode, maintenance mode, database driver and server version, cache store, queue connection and session driver. It never throws: an unreachable database reads "unavailable". It has its own ability because version numbers are reconnaissance.

## Assets

| Mode | Value | Served from | After an upgrade |
|---|---|---|---|
| Route (default) | `route` | the package's `resources/dist`, through `laranail-artisan-ui.asset` | nothing to do |
| Published | `published` | `public/vendor/artisan-ui`, after `vendor:publish --tag=laranail::artisan-ui-assets` | re-publish with `--force` |

Both URLs carry `?id=<12-character content hash>`. In route mode the response is `Cache-Control: public, max-age=31536000, immutable` with an ETag, so the browser caches it for a year and a new build is simply a new URL. Doctor fails when the bundle is missing and warns when published assets are stale.

The bundle has no runtime dependencies and loads nothing from a CDN. Both files are built from `resources/js` and `resources/css` by `npm run build` and committed.

## Theming

Views resolve through `Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView`: `laranail/artisan-ui::themes.<theme>.<view>`, falling back to the `default` theme **per view**. A custom theme only needs the views it changes.

```php
'theme' => 'acme',
```

The theme name must be a lowercase slug; anything else falls back to `default`, so it can never walk out of the themes directory. See [Publish and theme the views](../recipes/publish-and-theme-views.md).

Dark mode follows the operator's saved choice (stored in `localStorage` under `laranail-artisan-ui:theme`), otherwise the system preference, by toggling a `dark` class on `<html>`. A browser that refuses storage just forgets the choice.

Command output colours come from a fixed set of `lau-*` classes in `resources/css/artisan-ui.css` (`lau-fg-red`, `lau-bg-bright-black`, `lau-bold`, …). A theme can restyle them in its own stylesheet, served from the application's origin.

## The client

`resources/js/index.js` mounts one instance on `DOMContentLoaded` and exposes it as `window.laranailArtisanUi`. It progressively enhances the server-rendered pages:

- **Command list**: filters as you type (every whitespace-separated term must appear in the name or description, case-insensitively), opens matching groups, and mirrors the open group into `#group-<namespace>` so a link or reload returns to it.
- **List fields**: add and remove rows, always keeping one empty row.
- **Run form**: serialises the form to `{ arguments, options, confirm }`, runs the hooks, posts it, and handles every answer:

| Status | The form |
|---|---|
| `200` | renders the output segments and the status line (status, exit code, duration, error) |
| `422` | shows each field's error beside it, marks it invalid, and focuses the first |
| `423` | opens the password dialog, confirms, and runs again (nothing ran the first time) |
| `419` | fetches a fresh CSRF token and asks the operator to run again; **never replays the POST** |
| `409` | says the command is already running |
| `403`, `404`, other | a generic message; the server says no more |

Closing the password dialog (Esc or Cancel) while the password is still being checked cancels the run: nothing is sent even if the check then succeeds.

See [Events and hooks](events.md) and [Extending](extending.md).

## Accessibility

- A skip link to the main content.
- Native `<details>` for groups and sections and a native `<dialog>` for the password prompt: keyboard- and screen-reader-accessible without extra script.
- Every field has a `<label>`, and `aria-describedby` points at both its help text and its error. Invalid fields get `aria-invalid="true"` and the first one is focused.
- The status line is `role="status"` with `aria-live="polite"`; the form sets `aria-busy` while running and the button is disabled.
- The output `<pre>` is focusable, so it can be scrolled from the keyboard; long lines scroll horizontally.
- The dark-mode toggle reports its state with `aria-pressed`; risk badges carry their explanation as a title.
- Unavailable quick actions are marked `aria-disabled` and say why in text, not only by colour.

## Translations

Every string comes from `laranail/artisan-ui::messages.*`. Publish them to translate or reword:

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-translations
```

That writes `lang/vendor/laranail/artisan-ui/en/messages.php`; add a directory per locale. Client-side strings are passed to the script as a JSON data block, not inline script.

---

[← Docs index](../../README.md#documentation)
