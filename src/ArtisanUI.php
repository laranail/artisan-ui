<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI;

use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Testing\FakeRunner;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetGroup;
use Simtabi\Laranail\ArtisanUI\Core\Presets\QuickAction;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\CommandRunner;
use Simtabi\Laranail\ArtisanUI\Core\Output\DecoratorRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * The application-facing API, behind the `ArtisanUI` facade.
 *
 * Runtime registration for the extension points (quick actions, output decorators) and
 * read access to what the panel exposes. Authorization is not configured here: it is the
 * Gate abilities in Enums\Ability, defined in the application's own provider, which is where
 * upstream's `ArtisanUI::auth()` callback now lives.
 *
 * Registration methods return the instance, so a provider reads as one chain:
 *
 *     ArtisanUI::quickActions('deploy', 'Deploy', [...])->decorate('about', AboutDecorator::class);
 *
 * To decorate a service rather than a command's output, use the container:
 * `$this->app->extend(CommandRunner::class, fn ($runner) => new TimingRunner($runner))`.
 * Extenders run in registration order, so the last one registered wraps outermost.
 */
final readonly class ArtisanUI
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * @return array<string, CommandDefinition>
     */
    public function commands(): array
    {
        return $this->container->make(CommandRegistry::class)->all();
    }

    public function find(string $name): ?CommandDefinition
    {
        return $this->container->make(CommandRegistry::class)->find($name);
    }

    public function risk(string $name): CommandRisk
    {
        return $this->container->make(RiskClassifier::class)->classify($name);
    }

    /**
     * Add a quick-action group to the home screen.
     *
     * @param list<QuickAction> $actions
     */
    public function quickActions(string $key, string $label, array $actions, ?string $description = null): static
    {
        $this->container->make(PresetCatalog::class)->register(new PresetGroup($key, $label, $actions, $description));

        return $this;
    }

    /**
     * Rewrite the output of every command matching `$pattern`.
     *
     * @param class-string<OutputDecorator>|OutputDecorator $decorator
     */
    public function decorate(string $pattern, string|OutputDecorator $decorator): static
    {
        $this->container->make(DecoratorRegistry::class)->add($pattern, $decorator);

        return $this;
    }

    /**
     * Swap the command runner for a recording fake, so an application's own tests can drive
     * the panel without running anything. Every check around the runner (authorization,
     * validation, audit, events) still runs for real.
     */
    public function fake(?FakeRunner $runner = null): FakeRunner
    {
        $runner ??= new FakeRunner;

        $this->container->instance(CommandRunner::class, $runner);

        return $runner;
    }
}
