# Scope commands per user

Let different users run different commands, by deciding in the `run` ability, which receives the command and its validated input.

## Decide in the `run` ability

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

Gate::define(Ability::Run->value, function (User $user, CommandDefinition $command, array $input): bool {
    if ($user->isAdmin()) {
        return true;
    }

    // Support staff: caches only, never anything destructive.
    return $user->isSupport()
        && ArtisanUI::risk($command->name) === CommandRisk::Safe
        && in_array($command->namespace(), ['cache', 'config', 'route', 'view', 'optimize'], true);
});
```

The panel still lists every command to anyone who passes `access`; a refused run answers `403` and is logged. To hide commands from everyone, use the [command policy](../tools/command-policy.md) instead. See [Authorization](../tools/authorization.md#per-command-scoping).

---

[← Docs index](../../README.md#documentation)
