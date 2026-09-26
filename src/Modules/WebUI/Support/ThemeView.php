<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Resolves a panel view through the configured theme, falling back to the default theme for
 * any view a custom theme does not override (pabloleone's themes, made incremental).
 */
final readonly class ThemeView
{
    public const string NAMESPACE = 'laranail/artisan-ui';

    public function __construct(
        private Factory $views,
        private ArtisanUIConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function make(string $name, array $data = []): View
    {
        return $this->views->make($this->resolve($name), $data);
    }

    public function resolve(string $name): string
    {
        $themed = self::NAMESPACE . '::themes.' . $this->config->theme() . '.' . $name;

        return $this->views->exists($themed) ? $themed : self::NAMESPACE . '::themes.default.' . $name;
    }
}
