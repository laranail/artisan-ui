<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Presets;

final readonly class PresetGroup
{
    /**
     * @param list<QuickAction> $actions
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $actions,
        public ?string $description = null,
    ) {}

    /**
     * @param list<QuickAction> $actions
     */
    public function withActions(array $actions): self
    {
        return new self($this->key, $this->label, $actions, $this->description);
    }
}
