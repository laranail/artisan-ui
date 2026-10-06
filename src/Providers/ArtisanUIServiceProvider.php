<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Providers;

use Illuminate\Http\Request;
use Simtabi\Laranail\ArtisanUI\ArtisanUI;
use Illuminate\Console\Scheduling\Schedule;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\ArtisanUI\Doctor\Checks;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Simtabi\Laranail\ArtisanUI\Commands\TidyCommand;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Commands\DoctorCommand;
use Simtabi\Laranail\ArtisanUI\Commands\PolicyCommand;
use Simtabi\Laranail\ArtisanUI\Core\Audit\LogRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Audit\NullRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Policy\PanelAccess;
use Simtabi\Laranail\ArtisanUI\Core\Execution\SyncRunner;
use Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter;
use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandPolicy;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\RunRecorder;
use Simtabi\Laranail\ArtisanUI\Core\Output\SecretRedactor;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog;
use Simtabi\Laranail\ArtisanUI\Core\Audit\DatabaseRecorder;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Policy\DefaultAbilities;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets;
use Simtabi\Laranail\ArtisanUI\Core\Output\DecoratorRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Validation\InputValidator;
use Simtabi\Laranail\ArtisanUI\Core\Presets\MigrationExistsGuard;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Providers\WebUIServiceProvider;
use Simtabi\Laranail\Package\Tools\Support\Definitions\RateLimiterDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\AboutSectionDefinition;
use Simtabi\Laranail\Package\Tools\Support\Definitions\InstallCommandDefinition;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Middleware\EnsureArtisanUIAccess;

/**
 * Registers the headless core and the web module.
 *
 * Everything that names something in a shared registry is vendor-scoped: views and
 * translations as `laranail/artisan-ui::`, Blade tags as `laranail-artisan-ui::`, config at
 * `laranail.artisan-ui`, commands as `laranail::artisan-ui.*`, publish tags as
 * `laranail::artisan-ui-*`, and the middleware alias, rate limiters, route names and Gate
 * abilities under `laranail-artisan-ui`. tests/Feature/NamingConventionTest.php reads each
 * one back from the live registry.
 *
 * Modules each have their own provider; this one registers them. Only the web module exists
 * today.
 */
final class ArtisanUIServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/artisan-ui')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasAssets()
            // Published, never loaded automatically: the database audit driver is opt-in,
            // and the install command asks before migrating.
            ->hasMigration('create_laranail_artisan_ui_runs_table')
            ->hasCommands([
                DoctorCommand::class,
                PolicyCommand::class,
                TidyCommand::class,
            ])
            ->hasInstallCommand(
                InstallCommandDefinition::make()
                    ->named('laranail::artisan-ui.install')
                    ->visible()
                    ->publishes('config', 'migrations')
                    ->asksToRunMigrations()
                    ->step('explain access', static function (InstallCommand $command): void {
                        $command->newLine();
                        $command->line('Nobody can open the panel until you define who may. In a service provider:');
                        $command->newLine();
                        $command->line("    Gate::define('" . Ability::Access->value . "', fn (\$user) => \$user->isAdmin());");
                        $command->line("    Gate::define('" . Ability::Run->value . "', fn (\$user, \$command, \$input) => \$user->isAdmin());");
                        $command->newLine();
                        $command->line('Then set LARANAIL_ARTISAN_UI_ENABLED=true and run `php artisan laranail::artisan-ui.doctor`.');
                    }),
            )
            ->registerRouteMiddleware('laranail-artisan-ui', EnsureArtisanUIAccess::class)
            ->registerRateLimiter(
                RateLimiterDefinition::make('laranail-artisan-ui')
                    ->perMinute(static fn (Request $request): int => app(ArtisanUIConfig::class)->ratePerMinute())
                    ->byUser(),
            )
            ->registerRateLimiter(
                RateLimiterDefinition::make('laranail-artisan-ui-confirm')
                    ->perMinute(5)
                    ->byUser(),
            )
            ->hasDoctorChecks(new Checks($this->app)->all())
            // Evaluated once the schedule is resolved, so the configuration is final.
            ->schedulesUsing(static function (Schedule $schedule): void {
                $config = app(ArtisanUIConfig::class);

                if ($config->auditDriver() === AuditDriver::Database && $config->schedulesPrune()) {
                    $schedule->command('model:prune', ['--model' => [CommandRun::class]])
                        ->daily()
                        ->name('laranail-artisan-ui:prune-runs')
                        ->withoutOverlapping();
                }
            })
            ->hasAboutSection(
                AboutSectionDefinition::make('Artisan UI')
                    ->fieldsUsing(static function (): array {
                        $config = app(ArtisanUIConfig::class);

                        return [
                            'Enabled'      => $config->enabled() ? 'yes' : 'no',
                            'Path'         => '/' . $config->path(),
                            'Environments' => implode(', ', $config->environments()) ?: 'none',
                            'Audit'        => $config->auditDriver()->value,
                        ];
                    }),
            );
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ArtisanUIConfig::class);
        $this->app->singleton(RiskClassifier::class);
        $this->app->singleton(CommandPolicy::class);
        $this->app->singleton(InputValidator::class);
        $this->app->singleton(SecretRedactor::class);
        $this->app->singleton(AnsiFormatter::class);
        $this->app->singleton(DecoratorRegistry::class);
        $this->app->singleton(PresetCatalog::class);
        $this->app->singleton(MigrationExistsGuard::class);
        $this->app->singleton(DefaultAbilities::class);
        $this->app->singleton(PanelAccess::class);
        $this->app->singleton(ArtisanUI::class);

        // Per request: commands registered at runtime must be seen, and a long-lived worker
        // must not keep the first request's list forever.
        $this->app->scoped(CommandRegistry::class);

        $this->app->bind(CommandRunner::class, SyncRunner::class);

        $this->app->bind(RunRecorder::class, fn (): RunRecorder => match ($this->app->make(ArtisanUIConfig::class)->auditDriver()) {
            AuditDriver::Database => $this->app->make(DatabaseRecorder::class),
            AuditDriver::None     => new NullRecorder,
            AuditDriver::Log      => $this->app->make(LogRecorder::class),
        });

        $this->app->singleton(Assets::class, fn (): Assets => new Assets(
            $this->packagePath('resources/dist'),
            $this->app->make(ArtisanUIConfig::class),
        ));

        $this->app->register(WebUIServiceProvider::class);
    }

    public function packageBooted(): void
    {
        $this->app->make(DefaultAbilities::class)->define();
    }
}
