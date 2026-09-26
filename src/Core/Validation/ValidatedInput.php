<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Validation;

/**
 * Input that has passed InputValidator: every key is one the command defines, every value is
 * the right shape and within the configured size limits, and no global option is present.
 */
final readonly class ValidatedInput
{
    /**
     * @param array<string, string|list<string>> $arguments
     * @param array<string, bool|string|list<string>|null> $options null = a value-optional option given without a value
     */
    public function __construct(
        public array $arguments = [],
        public array $options = [],
    ) {}

    /**
     * The parameters for `Kernel::call()`: arguments by name, options as `--name`.
     *
     * @return array<string, bool|string|list<string>|null>
     */
    public function toParameters(): array
    {
        $parameters = $this->arguments;

        foreach ($this->options as $name => $value) {
            $parameters['--' . $name] = $value;
        }

        return $parameters;
    }

    /**
     * @return array{arguments: array<string, string|list<string>>, options: array<string, bool|string|list<string>|null>}
     */
    public function toArray(): array
    {
        return ['arguments' => $this->arguments, 'options' => $this->options];
    }
}
