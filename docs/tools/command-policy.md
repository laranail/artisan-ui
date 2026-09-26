# Command policy

The command policy, `Simtabi\Laranail\ArtisanUI\Core\Policy\CommandPolicy`, decides which commands the panel exposes at all, from three configuration keys.

## The keys

| Key | Default | Meaning |
|---|---|---|
| `commands.allow` | `null` | `null` lists every command. A list restricts the panel to the commands matching it. An **empty** list allows nothing. |
| `commands.deny` | `[]` | Commands never listed or runnable. |
| `commands.include_hidden` | `false` | Whether commands marked hidden are listed. |

## How a command is decided

1. A hidden command is out unless `include_hidden` is true.
2. A command matching any `deny` pattern is out. **Deny always wins.**
3. When `allow` is a list, a command matching none of its patterns is out.
4. A command whose [risk class](risk-levels.md) is forbidden is out.

What is left is listed, and only what is listed can be opened or run. The policy is applied to listing and execution through the same registry, so guessing a URL reaches nothing the list does not show.

## Pattern matching

Patterns are matched by `Simtabi\Laranail\ArtisanUI\Core\Support\CommandPattern`:

- `Str::is()` wildcards: `make:*`, `*:clear`, `queue:*`;
- a namespace pattern `name:*` **also** matches the bare command `name`, because that is what anyone writing `migrate:*` in a deny list means.

| Pattern | Matches | Does not match |
|---|---|---|
| `migrate:*` | `migrate`, `migrate:fresh`, `migrate:status` | `migrates` |
| `db:*` | `db`, `db:seed`, `db:wipe` | `dbx:run` |
| `*:clear` | `cache:clear`, `view:clear` | `optimize` |
| `about` | `about` | `about:x` |

The same matcher is used by the risk lists and the decorator patterns.

## Inspecting the effect

`laranail::artisan-ui.policy` prints every command with whether it is listed, its risk class, and why it is not listed:

```bash
php artisan laranail::artisan-ui.policy --listed
php artisan laranail::artisan-ui.policy --risk=destructive
```

The reason column reads `forbidden`, `hidden` or `allow/deny list`. See [Commands](commands.md).

## Policy is not authorization

The policy is application-wide: it decides what the panel can do for anyone. Deciding what a particular user may run is the `run` ability's job; see [Authorization](authorization.md) and [Scope commands per user](../recipes/scope-commands-per-user.md).

---

[← Docs index](../../README.md#documentation)
