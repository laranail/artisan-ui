<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Output;

use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\ArtisanUI\Core\Execution\RunResult;
use Simtabi\Laranail\ArtisanUI\Core\Support\CommandPattern;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Output decorators by command pattern, from configuration and from runtime registration.
 *
 * Every decorator whose pattern matches runs, configuration first, in declaration order.
 * Classes are resolved through the container, so a decorator can take dependencies.
 */
final class DecoratorRegistry
{
    /** @var array<string, list<class-string<OutputDecorator>|OutputDecorator>> */
    private array $registered = [];

    public function __construct(
        private readonly Container $container,
        private readonly ArtisanUIConfig $config,
    ) {}

    /**
     * @param class-string<OutputDecorator>|OutputDecorator $decorator
     */
    public function add(string $pattern, string|OutputDecorator $decorator): void
    {
        $this->registered[$pattern][] = $decorator;
    }

    public function apply(CommandDefinition $command, RunResult $result, string $output): string
    {
        $entries = [];

        foreach ($this->config->decorators() as $pattern => $class) {
            $entries[] = [$pattern, $class];
        }

        foreach ($this->registered as $pattern => $decorators) {
            foreach ($decorators as $decorator) {
                $entries[] = [$pattern, $decorator];
            }
        }

        foreach ($entries as [$pattern, $decorator]) {
            if (CommandPattern::matches($pattern, $command->name)) {
                $output = $this->resolve($decorator)->decorate($output, $command, $result);
            }
        }

        return $output;
    }

    private function resolve(string|OutputDecorator $decorator): OutputDecorator
    {
        if ($decorator instanceof OutputDecorator) {
            return $decorator;
        }

        $instance = $this->container->make($decorator);

        if (! $instance instanceof OutputDecorator) {
            throw new InvalidArgumentException(sprintf(
                'Output decorator [%s] must implement %s.',
                $decorator,
                OutputDecorator::class,
            ));
        }

        return $instance;
    }
}
