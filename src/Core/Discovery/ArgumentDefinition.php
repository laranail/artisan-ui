<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Discovery;

use Symfony\Component\Console\Input\InputArgument;

final readonly class ArgumentDefinition extends InputField
{
    public static function fromSymfony(InputArgument $argument): self
    {
        return new self(
            name: $argument->getName(),
            description: $argument->getDescription(),
            required: $argument->isRequired(),
            array: $argument->isArray(),
            default: self::normaliseDefault($argument->getDefault()),
        );
    }

    public function kind(): string
    {
        return 'argument';
    }

    public function isBoolean(): bool
    {
        return false;
    }

    /**
     * @return string|bool|int|float|list<mixed>|null
     */
    private static function normaliseDefault(mixed $default): string|bool|int|float|array|null
    {
        return match (true) {
            is_array($default)                     => array_values($default),
            is_scalar($default), $default === null => $default,
            default                                => null,
        };
    }
}
