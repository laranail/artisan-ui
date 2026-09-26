# Discovery

The command registry, `Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry`, turns the console kernel's commands into immutable `CommandDefinition` objects the panel lists, renders and validates against.

## What is discovered

The registry reads `Artisan::all()` through the console kernel and keeps a command only when:

- it is an `Illuminate\Console\Command` (Symfony's own `list`, `help` and `completion` describe the console itself and are left out);
- it is listed under its own name (aliases are skipped, so each command is described once);
- the [command policy](command-policy.md) lists it: not denied, allowed when an allow list is set, not hidden unless `commands.include_hidden` is true, and not [forbidden](risk-levels.md).

The same registry backs the listing **and** the detail and run endpoints, so a command that is not listed answers `404` however it is reached.

The registry is bound **scoped**: built once per request, so a command registered at runtime is seen and a long-lived worker never keeps the first request's list. Within a request, call `flush()` after registering a command if you need it seen immediately.

## `CommandDefinition`

An immutable description of one command, with no reference back to the command instance. Execution goes through the console kernel by name, so a second run never reuses state the first left behind.

| Property / method | Type | Meaning |
|---|---|---|
| `name` | `string` | The command name, e.g. `migrate:fresh`. |
| `description` | `string` | The one-line description. |
| `help` | `string` | The processed help text; empty when it only repeats the description. |
| `synopsis` | `string` | Symfony's usage line. |
| `hidden` | `bool` | Whether the command is marked hidden. |
| `aliases` | `list<string>` | Its aliases. |
| `arguments` | `list<ArgumentDefinition>` | Its arguments, in order. |
| `options` | `list<OptionDefinition>` | Its own options, without the global ones. |
| `namespace()` | `?string` | Everything before the first `:`, or `null`. |
| `argument($name)`, `option($name)` | `?ArgumentDefinition`, `?OptionDefinition` | Look up one field. |
| `hasRequiredArguments()` | `bool` | Whether the arguments panel starts open. |
| `toArray()` | `array` | A plain array for serialisation. |

`CommandDefinition::GLOBAL_OPTIONS` lists the Symfony options every command inherits and the panel removes: `help`, `quiet`, `silent`, `verbose`, `version`, `ansi`, `no-ansi`, `no-interaction`, `env`.

## Fields

Arguments and options share `InputField`:

| Property / method | Meaning |
|---|---|
| `name`, `description` | As declared. |
| `required` | For an argument, it must be given. For an option, its **value** is required once the option is given; an option itself is never mandatory. |
| `array` | Accepts a list of values. |
| `default` | Scalar, list or `null`. |
| `kind()` | `argument` or `option`: the section the input arrives under. |
| `isBoolean()` | `true` for an option that takes no value (a flag). Arguments are never boolean. |
| `defaultForDisplay()` | The default as placeholder text. |
| `domId()` | A DOM-safe id for the form control. |

`OptionDefinition` adds `shortcut`, `acceptsValue`, `negatable` and `parameterKey()` (`--name`).

## Grouping

`grouped()` returns `namespace => list<CommandDefinition>`, as `php artisan list` shows them:

- commands without a namespace come first, under the key `''` (shown as **General**);
- a command named exactly like a namespace (`migrate`, `db`) is placed at the head of that namespace's group rather than among the un-namespaced ones.

## Reading it from an application

```php
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

$commands = ArtisanUI::commands();          // array<string, CommandDefinition>, sorted by name
$migrate  = ArtisanUI::find('migrate');     // ?CommandDefinition, null when not listed
```

---

[← Docs index](../../README.md#documentation)
