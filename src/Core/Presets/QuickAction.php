<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Presets;

/**
 * A pre-filled command form (dev-arindam-roy's one-click buttons, made safe).
 *
 * A quick action never runs anything by itself. It links to the command's form with these
 * values filled in; running it goes through the one execute endpoint and every check that
 * guards it, including the destructive confirmation.
 */
final readonly class QuickAction
{
    /**
     * @param array<string, string|list<string>> $arguments
     * @param array<string, bool|string|list<string>> $options
     */
    public function __construct(
        public string $label,
        public string $command,
        public array $arguments = [],
        public array $options = [],
        public ?string $description = null,
        public ?string $unavailableReason = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $label = $data['label'] ?? null;
        $command = $data['command'] ?? null;

        if (! is_string($label) || ! is_string($command) || $label === '' || $command === '') {
            return null;
        }

        $arguments = is_array($data['arguments'] ?? null) ? $data['arguments'] : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $description = $data['description'] ?? null;

        /** @var array<string, string|list<string>> $arguments */
        /** @var array<string, bool|string|list<string>> $options */
        return new self($label, $command, $arguments, $options, is_string($description) ? $description : null);
    }

    public function unavailable(string $reason): self
    {
        return new self($this->label, $this->command, $this->arguments, $this->options, $this->description, $reason);
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason === null;
    }

    /**
     * The query string that pre-fills the command form.
     *
     * @return array{arguments?: array<string, string|list<string>>, options?: array<string, string|list<string>>}
     */
    public function prefill(): array
    {
        $query = [];

        if ($this->arguments !== []) {
            $query['arguments'] = $this->arguments;
        }

        if ($this->options !== []) {
            $query['options'] = array_map(
                static fn (bool|string|array $value): string|array => $value === true ? '1' : ($value === false ? '0' : $value),
                $this->options,
            );
        }

        return $query;
    }
}
