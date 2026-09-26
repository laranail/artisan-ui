<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Discovery;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandPolicy;

/**
 * The commands the panel exposes, already filtered by the command policy.
 *
 * Only `Illuminate\Console\Command` instances are considered. Symfony's own `list`, `help`
 * and `completion` describe the console itself and have no use in a web panel, which is also
 * what upstream did; hidden commands are left out unless the configuration asks for them.
 *
 * Resolved per request (bound as scoped), so a command registered at runtime is seen and the
 * definitions are built once per request rather than once per lookup.
 */
final class CommandRegistry
{
    /** @var array<string, CommandDefinition>|null */
    private ?array $definitions = null;

    public function __construct(
        private readonly Kernel $artisan,
        private readonly CommandPolicy $policy,
    ) {}

    /**
     * @return array<string, CommandDefinition> keyed and sorted by name
     */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        foreach ($this->artisan->all() as $name => $command) {
            // Aliases are listed under their own key; describe each command once.
            if (! $command instanceof Command || $command->getName() !== $name) {
                continue;
            }

            if (! $this->policy->isListed($name, $command->isHidden())) {
                continue;
            }

            $definitions[$name] = CommandDefinition::fromCommand($command);
        }

        ksort($definitions);

        return $this->definitions = $definitions;
    }

    public function find(string $name): ?CommandDefinition
    {
        return $this->all()[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return $this->find($name) instanceof CommandDefinition;
    }

    /**
     * @return array<string, list<CommandDefinition>> namespace => commands; the key for
     *                                                commands without a namespace is ''
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $definition) {
            $groups[$definition->namespace() ?? ''][] = $definition;
        }

        // A command named exactly like a namespace (`migrate`, `db`) belongs with it rather
        // than among the un-namespaced ones.
        foreach ($groups[''] ?? [] as $index => $definition) {
            if (isset($groups[$definition->name])) {
                array_unshift($groups[$definition->name], $definition);
                unset($groups[''][$index]);
            }
        }

        $groups = array_map(array_values(...), $groups);

        if (($groups[''] ?? null) === []) {
            unset($groups['']);
        }

        // Un-namespaced commands first, as `php artisan list` shows them.
        uksort($groups, static fn (string $a, string $b): int => [$a !== '', $a] <=> [$b !== '', $b]);

        return $groups;
    }

    /** Forget the cached definitions, e.g. after a command is registered at runtime. */
    public function flush(): void
    {
        $this->definitions = null;
    }
}
