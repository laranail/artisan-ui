# Enable run history

Record every run in the database, which also switches on the History screen and rerun.

## Switch the driver and migrate

```bash
php artisan vendor:publish --tag=laranail::artisan-ui-migrations
php artisan migrate
```

```dotenv
LARANAIL_ARTISAN_UI_AUDIT=database
# Optional, but recommended if migrate:fresh or db:wipe are run from the panel:
LARANAIL_ARTISAN_UI_AUDIT_CONNECTION=audit
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;

Gate::define(Ability::ViewHistory->value, fn (User $user): bool => $user->isAdmin());
```

History appears at `/artisan/history`. Runs older than `audit.retention_days` (90) are pruned daily, provided the scheduler runs. See [Audit and history](../tools/audit-history.md).

---

[← Docs index](../../README.md#documentation)
