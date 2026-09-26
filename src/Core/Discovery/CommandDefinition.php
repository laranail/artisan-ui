<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Discovery;

use Illuminate\Console\Command;

/**
 * An immutable description of one Artisan command: everything the panel shows and the
 * validator checks, with no reference back to the command instance.
 *
 * Upstream kept a live `Illuminate\Console\Command` and called `run()` on it directly. That
 * instance is a container singleton, so a second run reused whatever state the first left
 * behind. Execution now goes through the console kernel by name, and this object is only a
 * description.
 */
final readonly class CommandDefinition
{
    /**
     * Options Symfony adds to every command. They are not the command's own, and `--env` in
     * particular would let a request switch the environment the command runs in.
     */
    public const array GLOBAL_OPTIONS = [
        'help',
        'quiet',
        'silent',
        'verbose',
        'version',
        'ansi',
        'no-ansi',
        'no-interaction',
        'env',
    ];

    /**
     * @param list<ArgumentDefinition> $arguments
     * @param list<OptionDefinition> $options
     * @param list<string> $aliases
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $help,
        public string $synopsis,
        public bool $hidden,
        public array $aliases,
        public array $arguments,
        public array $options,
    ) {}

    public static function fromCommand(Command $command): self
    {
        $definition = $command->getDefinition();

        $options = [];

        foreach ($definition->getOptions() as $option) {
            if (! in_array($option->getName(), self::GLOBAL_OPTIONS, true)) {
                $options[] = OptionDefinition::fromSymfony($option);
            }
        }

        $help = $command->getProcessedHelp();

        return new self(
            name: (string) $command->getName(),
            description: $command->getDescription(),
            // Symfony falls back to the description when no help is set; showing it twice
            // says nothing new.
            help: $help === $command->getDescription() ? '' : $help,
            synopsis: $command->getSynopsis(),
            hidden: $command->isHidden(),
            aliases: array_values($command->getAliases()),
            arguments: array_values(array_map(
                ArgumentDefinition::fromSymfony(...),
                $definition->getArguments(),
            )),
            options: $options,
        );
    }

    /**
     * The namespace the command belongs to: everything before the first `:`, as
     * `php artisan list` groups them, or null for a command with no namespace.
     */
    public function namespace(): ?string
    {
        if (! str_contains($this->name, ':')) {
            return null;
        }

        return strstr($this->name, ':', true) ?: null;
    }

    public function argument(string $name): ?ArgumentDefinition
    {
        foreach ($this->arguments as $argument) {
            if ($argument->name === $name) {
                return $argument;
            }
        }

        return null;
    }

    public function option(string $name): ?OptionDefinition
    {
        foreach ($this->options as $option) {
            if ($option->name === $name) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Whether the arguments panel should start open. Upstream PR #2's intent; its view
     * rendered `false` as an empty string and produced invalid JavaScript.
     */
    public function hasRequiredArguments(): bool
    {
        return array_any($this->arguments, fn (ArgumentDefinition $argument): bool => $argument->required);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'namespace'   => $this->namespace(),
            'description' => $this->description,
            'synopsis'    => $this->synopsis,
            'arguments'   => array_map(static fn (ArgumentDefinition $a): array => $a->toArray(), $this->arguments),
            'options'     => array_map(static fn (OptionDefinition $o): array => $o->toArray(), $this->options),
        ];
    }
}
