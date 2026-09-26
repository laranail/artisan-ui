<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use RuntimeException;
use Simtabi\Laranail\ArtisanUI\Enums\AssetMode;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Locates the panel's built stylesheet and script and produces their URLs.
 *
 * The files are a fixed map, never a path built from a request, so the asset route has no
 * traversal surface. The content hash in the URL is what lets the route answer with an
 * immutable, year-long cache: a new build is a new URL. The pattern is confetti's.
 */
final class Assets
{
    public const string SCRIPT = 'artisan-ui.js';

    public const string STYLE = 'artisan-ui.css';

    /** @var array<string, string> public name => content type */
    private const array FILES = [
        self::SCRIPT => 'application/javascript; charset=utf-8',
        self::STYLE  => 'text/css; charset=utf-8',
    ];

    /** @var array<string, string> */
    private array $hashes = [];

    public function __construct(
        private readonly string $directory,
        private readonly ArtisanUIConfig $config,
    ) {}

    /** @return list<string> */
    public static function filenames(): array
    {
        return array_keys(self::FILES);
    }

    public static function isKnown(string $file): bool
    {
        return isset(self::FILES[$file]);
    }

    public function path(string $file): ?string
    {
        return self::isKnown($file) ? $this->directory . '/' . $file : null;
    }

    public function exists(string $file): bool
    {
        $path = $this->path($file);

        return $path !== null && is_file($path);
    }

    public function contentType(string $file): string
    {
        return self::FILES[$file] ?? 'application/octet-stream';
    }

    public function hash(string $file): string
    {
        if (isset($this->hashes[$file])) {
            return $this->hashes[$file];
        }

        $path = $this->path($file);

        if ($path === null || ! is_file($path)) {
            return 'dev';
        }

        $hash = hash_file('xxh128', $path);

        return $this->hashes[$file] = $hash === false ? 'dev' : substr($hash, 0, 12);
    }

    public function contents(string $file): string
    {
        $path = $this->path($file);
        $contents = $path !== null && is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("laranail/artisan-ui: [{$file}] has not been built. Run `npm install && npm run build` in the package.");
        }

        return $contents;
    }

    public function url(string $file): string
    {
        if ($this->config->assetMode() === AssetMode::Published) {
            return asset('vendor/artisan-ui/' . $file) . '?id=' . $this->hash($file);
        }

        return route('laranail-artisan-ui.asset', ['file' => $file, 'id' => $this->hash($file)]);
    }

    public function directory(): string
    {
        return $this->directory;
    }
}
