# Add a quick action

Put a pre-filled command form one click away on the home screen.

## Register a group

```php
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Facades\ArtisanUI;

// In a service provider's boot():
ArtisanUI::quickActions('support', 'Support', [
    new QuickAction('Retry all failed jobs', 'queue:retry', arguments: ['id' => ['all']]),
    new QuickAction('Seed demo data', 'db:seed', options: ['class' => 'DemoSeeder']),
]);
```

Or in `presets.groups` in the config. The action opens the form; **Run** still goes through every check, and an action whose command is not listed is dropped. See [Quick actions](../tools/quick-actions.md).

---

[← Docs index](../../README.md#documentation)
