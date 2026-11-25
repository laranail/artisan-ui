<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use Illuminate\Http\Request;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\InputField;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Values to pre-fill a command form with, from the query string a quick action or a history
 * rerun links with.
 *
 * Only keys the command defines are kept, and only text values. This never runs anything and
 * never trusts the values; the form is submitted, and validated, like any other.
 *
 * A value equal to the redaction mask is dropped: a history rerun carries the recorded input,
 * in which secrets are masked, and the mask must be typed over rather than submitted.
 */
final class Prefill
{
    /**
     * @return array<string, string|list<string>> keyed `arguments.<name>` / `options.<name>`, plus
     *                                            `options-present.<name>` for a valueless option
     */
    public static function fromRequest(Request $request, CommandDefinition $command, string $mask = ''): array
    {
        return self::fromInput([
            'arguments' => $request->query('arguments'),
            'options'   => $request->query('options'),
        ], $command, $mask);
    }

    /**
     * The form values for a run recorded in history: its arguments and options as stored (already
     * redacted), read on the server, so a rerun link need carry only the run id (AUI-13).
     *
     * @param array<string, mixed>|null $arguments
     * @param array<string, mixed>|null $options
     */
    public static function fromRun(?array $arguments, ?array $options, CommandDefinition $command, string $mask = ''): array
    {
        return self::fromInput([
            'arguments' => self::asQuery($arguments ?? []),
            'options'   => self::asQuery($options ?? []),
        ], $command, $mask);
    }

    /**
     * Recorded values as a query string would carry them, so a rerun by id fills the form exactly
     * as the old query-string link did: `true` is `'1'`, `false` is `'0'`, a number is its digits,
     * and `null` stays a present, empty key.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private static function asQuery(array $values): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            is_array($value)  => self::asQuery($value),
            is_bool($value)   => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            default           => null,
        }, $values);
    }

    /**
     * @param array{arguments: mixed, options: mixed} $sections
     */
    private static function fromInput(array $sections, CommandDefinition $command, string $mask): array
    {
        $values = [];

        foreach (['arguments' => $command->arguments, 'options' => $command->options] as $section => $fields) {
            $input = $sections[$section];

            if (! is_array($input)) {
                continue;
            }

            /** @var InputField $field */
            foreach ($fields as $field) {
                $value = $input[$field->name] ?? null;

                // A recorded `--no-name` comes back as `no-name=1`: the negatable select's "no".
                if ($field instanceof OptionDefinition && $field->negatable && $value === null && isset($input['no-' . $field->name])) {
                    $values[$section . '.' . $field->name] = 'no';

                    continue;
                }

                // A value-optional option given with no value comes back as a present, empty key
                // (ConvertEmptyStringsToNull makes it null): tick its box.
                if ($field instanceof OptionDefinition && $field->acceptsValue && ! $field->required && ! $field->array && array_key_exists($field->name, $input) && ($value === '' || $value === null)) {
                    $values['options-present.' . $field->name] = '1';

                    continue;
                }

                $keep = static fn (mixed $item): bool => is_string($item) && ($mask === '' || $item !== $mask);

                if (is_string($value) && $keep($value)) {
                    $values[$section . '.' . $field->name] = $field->array ? [$value] : $value;
                } elseif (is_array($value) && $field->array) {
                    $values[$section . '.' . $field->name] = array_values(array_filter($value, $keep));
                }
            }
        }

        return $values;
    }
}
