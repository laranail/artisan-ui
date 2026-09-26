<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Discovery;

/**
 * One argument or option of a command, detached from Symfony's input objects.
 *
 * The panel renders these and the validator checks against them, so neither needs a live
 * command instance, and nothing downstream can reach back into the command through them.
 */
abstract readonly class InputField
{
    /**
     * @param string|bool|int|float|list<mixed>|null $default
     */
    public function __construct(
        public string $name,
        public string $description,
        public bool $required,
        public bool $array,
        public string|bool|int|float|array|null $default,
    ) {}

    /** `argument` or `option`: the key the input arrives under, singular. */
    abstract public function kind(): string;

    /** Whether the field is a flag with no value. */
    abstract public function isBoolean(): bool;

    /** The default as text for a placeholder, or an empty string when there is none. */
    public function defaultForDisplay(): string
    {
        return match (true) {
            is_string($this->default)                        => $this->default,
            is_int($this->default), is_float($this->default) => (string) $this->default,
            is_array($this->default)                         => implode(', ', array_filter($this->default, is_scalar(...))),
            default                                          => '',
        };
    }

    /** A DOM-safe id for the field's form control. */
    public function domId(): string
    {
        return 'laranail-artisan-ui-' . $this->kind() . '-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $this->name);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind'        => $this->kind(),
            'name'        => $this->name,
            'description' => $this->description,
            'required'    => $this->required,
            'array'       => $this->array,
            'boolean'     => $this->isBoolean(),
            'default'     => $this->default,
        ];
    }
}
