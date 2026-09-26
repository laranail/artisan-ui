<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

/**
 * The application `composer serve` boots: what a host application would write to put the
 * panel in front of its admins. Development only; export-ignored.
 */
final class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config()->set('auth.providers.users.model', User::class);
        config()->set('laranail.artisan-ui.enabled', true);
        config()->set('laranail.artisan-ui.environments', ['local', 'testing', 'workbench']);
        config()->set('laranail.artisan-ui.audit.driver', 'database');
    }

    public function boot(): void
    {
        // Who may do what. In an application this is the only part that is really needed.
        Gate::define(Ability::Access->value, static fn (User $user): bool => $user->is_admin);
        Gate::define(Ability::Run->value, static fn (User $user): bool => $user->is_admin);
        Gate::define(Ability::ViewHistory->value, static fn (User $user): bool => $user->is_admin);
        Gate::define(Ability::ViewEnvironment->value, static fn (User $user): bool => $user->is_admin);

        // So `down` from the panel does not lock the panel out.
        PreventRequestsDuringMaintenance::except('artisan*');
    }
}
