<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Validation;

use Simtabi\Laranail\ArtisanUI\Core\Discovery\InputField;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\ArgumentDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\InvalidCommandInput;

/**
 * Checks request input against a command's definition before anything runs.
 *
 * This is pabloleone's ValidCommandParameters rule, extended: upstream passed whatever the
 * request held straight into ArrayInput. Here an unknown key is an error rather than an
 * exception from deep inside Symfony, a global option such as `--env` is refused outright,
 * values are normalised (trimmed, empty dropped, a lone string accepted for an array field)
 * and every value is bounded by the configured limits.
 */
final readonly class InputValidator
{
    private const array TRUTHY = [true, 1, '1', 'true', 'on', 'yes'];

    private const array FALSY = [false, 0, '0', 'false', 'off', 'no', '', null];

    public function __construct(
        private ArtisanUIConfig $config,
    ) {}

    /**
     * @throws InvalidCommandInput
     */
    public function validate(CommandDefinition $command, mixed $arguments, mixed $options): ValidatedInput
    {
        $errors = [];

        $arguments = $this->section('arguments', $arguments, $errors);
        $options = $this->section('options', $options, $errors);

        $validArguments = [];

        foreach ($arguments as $name => $value) {
            if (! $command->argument($name) instanceof ArgumentDefinition) {
                $errors['arguments.' . $name][] = $this->message('unknown_argument', ['name' => $name]);
            }
        }

        foreach ($command->arguments as $argument) {
            $value = $this->value($argument, $arguments[$argument->name] ?? null, 'arguments', $errors);

            if ($value === null || $value === []) {
                if ($argument->required) {
                    $errors['arguments.' . $argument->name][] = $this->message('required', ['name' => $argument->name]);
                }

                continue;
            }

            $validArguments[$argument->name] = $value;
        }

        $validOptions = [];

        foreach ($options as $name => $value) {
            $name = ltrim($name, '-');

            if (in_array($name, CommandDefinition::GLOBAL_OPTIONS, true)) {
                $errors['options.' . $name][] = $this->message('global_option', ['name' => $name]);

                continue;
            }

            $option = $command->option($name);

            // `no-foo` for a negatable `--foo` is the negation, not an unknown option.
            if (! $option instanceof OptionDefinition && str_starts_with($name, 'no-')) {
                $negated = $command->option(substr($name, 3));

                if ($negated instanceof OptionDefinition && $negated->negatable) {
                    if (in_array($value, self::TRUTHY, true)) {
                        $validOptions[$name] = true;
                    } elseif (! in_array($value, self::FALSY, true)) {
                        $errors['options.' . $negated->name][] = $this->message('flag_with_value', ['name' => $name]);
                    }

                    continue;
                }
            }

            if (! $option instanceof OptionDefinition) {
                $errors['options.' . $name][] = $this->message('unknown_option', ['name' => $name]);

                continue;
            }

            if ($option->isBoolean()) {
                // The form's three-way control for a negatable flag sends `no`.
                if ($option->negatable && $value === 'no') {
                    $validOptions['no-' . $name] = true;
                } elseif (in_array($value, self::TRUTHY, true)) {
                    $validOptions[$name] = true;
                } elseif (! in_array($value, self::FALSY, true)) {
                    $errors['options.' . $name][] = $this->message('flag_with_value', ['name' => $name]);
                }

                continue;
            }

            // A value-optional option ticked without a value: present, valueless.
            if ($value === true && ! $option->required && ! $option->array) {
                $validOptions[$name] = null;

                continue;
            }

            $normalised = $this->value($option, $value, 'options', $errors);

            if ($normalised !== null && $normalised !== []) {
                $validOptions[$name] = $normalised;
            }
        }

        if ($errors !== []) {
            throw new InvalidCommandInput($errors);
        }

        return new ValidatedInput($validArguments, $validOptions);
    }

    /**
     * @param array<string, string|int> $replace
     */
    private function message(string $key, array $replace): string
    {
        $message = __('laranail/artisan-ui::messages.validation.' . $key, $replace);

        return is_string($message) ? $message : $key;
    }

    /**
     * @param array<string, list<string>> $errors
     *
     * @return array<string, mixed>
     */
    private function section(string $key, mixed $value, array &$errors): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (! is_array($value) || array_is_list($value)) {
            $errors[$key][] = $this->message('not_an_object', ['section' => $key]);

            return [];
        }

        $clean = [];

        foreach ($value as $name => $item) {
            $clean[(string) $name] = $item;
        }

        return $clean;
    }

    /**
     * @param array<string, list<string>> $errors
     *
     * @return string|list<string>|null
     */
    private function value(InputField $field, mixed $value, string $section, array &$errors): string|array|null
    {
        $key = $section . '.' . $field->name;
        $maxLength = $this->config->limit('max_value_length', 1_000);

        if ($field->array) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $value = [$value];
            }

            if ($value === null) {
                return [];
            }

            if (! is_array($value)) {
                $errors[$key][] = $this->message('not_a_list', ['name' => $field->name]);

                return null;
            }

            $items = [];

            foreach ($value as $item) {
                if (! is_string($item) && ! is_int($item) && ! is_float($item)) {
                    $errors[$key][] = $this->message('list_item_text', ['name' => $field->name]);

                    return null;
                }

                $item = trim((string) $item);

                if ($item === '') {
                    continue;
                }

                if (mb_strlen($item) > $maxLength) {
                    $errors[$key][] = $this->message('item_too_long', ['name' => $field->name, 'max' => $maxLength]);

                    return null;
                }

                $items[] = $item;
            }

            $maxItems = $this->config->limit('max_array_items', 50);

            if (count($items) > $maxItems) {
                $errors[$key][] = $this->message('too_many_items', ['name' => $field->name, 'max' => $maxItems]);

                return null;
            }

            return $items;
        }

        if ($value === null) {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $errors[$key][] = $this->message('not_text', ['name' => $field->name]);

            return null;
        }

        $value = trim((string) $value);

        if (mb_strlen($value) > $maxLength) {
            $errors[$key][] = $this->message('too_long', ['name' => $field->name, 'max' => $maxLength]);

            return null;
        }

        return $value === '' ? null : $value;
    }
}
