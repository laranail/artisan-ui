<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Discovery;

use Symfony\Component\Console\Input\InputOption;

final readonly class OptionDefinition extends InputField
{
    /**
     * @param string|bool|int|float|list<mixed>|null $default
     */
    public function __construct(
        string $name,
        string $description,
        bool $required,
        bool $array,
        string|bool|int|float|array|null $default,
        public ?string $shortcut,
        public bool $acceptsValue,
        public bool $negatable,
    ) {
        parent::__construct($name, $description, $required, $array, $default);
    }

    public static function fromSymfony(InputOption $option): self
    {
        $default = $option->getDefault();

        return new self(
            name: $option->getName(),
            description: $option->getDescription(),
            // "Required" for an option means its value is required once the option is given;
            // an option itself is never mandatory.
            required: $option->isValueRequired(),
            array: $option->isArray(),
            default: match (true) {
                is_array($default)                     => array_values($default),
                is_scalar($default), $default === null => $default,
                default                                => null,
            },
            shortcut: $option->getShortcut(),
            acceptsValue: $option->acceptValue(),
            negatable: $option->isNegatable(),
        );
    }

    public function kind(): string
    {
        return 'option';
    }

    public function isBoolean(): bool
    {
        return ! $this->acceptsValue;
    }

    /** The key Symfony's ArrayInput expects. */
    public function parameterKey(): string
    {
        return '--' . $this->name;
    }
}
