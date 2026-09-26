<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Presets;

use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;

/**
 * The quick-action groups shown on the home screen.
 *
 * Built-in groups cover what dev-arindam-roy's panel hard-coded: caches, storage, database,
 * the framework's table migrations and the common generators. Applications add groups
 * through `presets.groups` or `ArtisanUI::quickActions()`. An action is dropped when its
 * command is not listed, so a quick action can never surface a command the policy hides.
 *
 * A singleton, because runtime registrations have to outlive the request that made them;
 * the command registry is per-request, so it is resolved when the groups are read.
 */
final class PresetCatalog
{
    /** @var list<PresetGroup> */
    private array $registered = [];

    public function __construct(
        private readonly ArtisanUIConfig $config,
        private readonly Container $container,
        private readonly MigrationExistsGuard $guard,
    ) {}

    /**
     * @return list<PresetGroup>
     */
    public static function defaults(): array
    {
        $a = static fn (string $label, string $command, array $options = [], array $arguments = [], ?string $description = null): QuickAction => new QuickAction($label, $command, $arguments, $options, $description);

        return [
            new PresetGroup('caches', 'Caches', [
                $a('Optimize', 'optimize', description: 'Cache config, routes, views and events.'),
                $a('Clear optimizations', 'optimize:clear'),
                $a('Clear application cache', 'cache:clear'),
                $a('Cache config', 'config:cache'),
                $a('Clear config cache', 'config:clear'),
                $a('Cache routes', 'route:cache'),
                $a('Clear route cache', 'route:clear'),
                $a('Cache views', 'view:cache'),
                $a('Clear compiled views', 'view:clear'),
                $a('Cache events', 'event:cache'),
                $a('Clear event cache', 'event:clear'),
            ]),
            new PresetGroup('storage', 'Storage', [
                $a('Link storage', 'storage:link'),
                $a('Re-link storage', 'storage:link', ['force' => true], description: 'Recreate the links even if they exist.'),
                $a('Unlink storage', 'storage:unlink'),
            ]),
            new PresetGroup('database', 'Database', [
                $a('Migration status', 'migrate:status'),
                $a('Migrate', 'migrate'),
                $a('Migrate and seed', 'migrate', ['seed' => true]),
                $a('Roll back last batch', 'migrate:rollback'),
                $a('Roll back one step', 'migrate:rollback', ['step' => '1']),
                $a('Fresh and seed', 'migrate:fresh', ['seed' => true], description: 'Drops every table first.'),
                $a('Seed', 'db:seed'),
                $a('Database overview', 'db:show'),
            ]),
            new PresetGroup('tables', 'Framework tables', array_map(
                static fn (string $command, string $table): QuickAction => $a("Create {$table} table migration", $command),
                array_keys(MigrationExistsGuard::TABLES),
                array_values(MigrationExistsGuard::TABLES),
            ), 'Generate the migration for a table the framework uses. Greyed out when one already exists.'),
            new PresetGroup('generators', 'Generators', [
                $a('Model with migration, factory, seeder and controller', 'make:model', ['migration' => true, 'factory' => true, 'seed' => true, 'controller' => true]),
                $a('Resource controller', 'make:controller', ['resource' => true]),
                $a('API controller', 'make:controller', ['api' => true]),
                $a('Invokable controller', 'make:controller', ['invokable' => true]),
                $a('Migration', 'make:migration'),
                $a('Form request', 'make:request'),
                $a('Policy', 'make:policy'),
                $a('Job', 'make:job'),
                $a('Event', 'make:event'),
                $a('Listener', 'make:listener'),
                $a('Mailable', 'make:mail'),
                $a('Notification', 'make:notification'),
                $a('Middleware', 'make:middleware'),
                $a('Command', 'make:command'),
                $a('Seeder', 'make:seeder'),
                $a('Factory', 'make:factory'),
            ]),
            new PresetGroup('maintenance', 'Maintenance', [
                $a('Generate application key', 'key:generate', description: 'Signs everyone out and invalidates encrypted data.'),
                $a('Maintenance mode (with secret)', 'down', ['secret' => ''], description: 'Set a secret, or the panel locks itself out too.'),
                $a('Bring application up', 'up'),
                $a('About', 'about'),
            ]),
        ];
    }

    public function register(PresetGroup $group): void
    {
        $this->registered[] = $group;
    }

    /**
     * @return list<PresetGroup> only groups with at least one listed command
     */
    public function groups(): array
    {
        if (! $this->config->presetsEnabled()) {
            return [];
        }

        $registry = $this->container->make(CommandRegistry::class);
        $groups = [];

        foreach ([...self::defaults(), ...$this->configured(), ...$this->registered] as $group) {
            $actions = [];

            foreach ($group->actions as $action) {
                if (! $registry->has($action->command)) {
                    continue;
                }

                $reason = $this->guard->reasonFor($action->command);

                $actions[] = $reason === null ? $action : $action->unavailable($reason);
            }

            if ($actions !== []) {
                $groups[] = $group->withActions($actions);
            }
        }

        return $groups;
    }

    /**
     * @return list<PresetGroup>
     */
    private function configured(): array
    {
        $groups = [];

        foreach ($this->config->presetGroups() as $key => $group) {
            if (! is_array($group)) {
                continue;
            }

            $actions = [];

            foreach (is_array($group['actions'] ?? null) ? $group['actions'] : [] as $action) {
                $action = is_array($action) ? QuickAction::fromArray($action) : null;

                if ($action instanceof QuickAction) {
                    $actions[] = $action;
                }
            }

            $label = $group['label'] ?? null;
            $description = $group['description'] ?? null;

            $groups[] = new PresetGroup(
                (string) $key,
                is_string($label) ? $label : (string) $key,
                $actions,
                is_string($description) ? $description : null,
            );
        }

        return $groups;
    }
}
