# Grant access to admins

Let administrators open the panel and run commands, and nobody else.

## Define the abilities

In a service provider's `boot()`:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

Gate::define(Ability::Access->value, fn (User $user): bool => $user->isAdmin());
Gate::define(Ability::Run->value, fn (User $user): bool => $user->isAdmin());
Gate::define(Ability::ViewHistory->value, fn (User $user): bool => $user->isAdmin());
Gate::define(Ability::ViewEnvironment->value, fn (User $user): bool => $user->isAdmin());
```

Then `php artisan laranail::artisan-ui.doctor` should report "Every ability is defined by the application." An ability you leave out keeps denying. See [Authorization](../tools/authorization.md).

---

[← Docs index](../../README.md#documentation)
