<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Output;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Contracts\Config\Repository;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;

/**
 * Keeps secrets out of what the panel shows and what the audit trail stores.
 *
 * Three passes over output, cheapest first:
 *
 *  1. the literal value of every environment variable whose name looks secret
 *     (`DB_PASSWORD`, `APP_KEY`, `STRIPE_SECRET` ...), wherever it appears;
 *  2. `NAME=value` lines whose name looks secret, which is what `env`-style dumps print;
 *  3. the password part of `scheme://user:password@host` URLs.
 *
 * Recorded input is masked by key: an option called `--password` is stored masked whatever
 * its value. None of this makes it safe to expose a command that prints secrets, and the
 * docs say so; it keeps an accidental one from landing in a log and a browser.
 *
 * Modelled on env-kit's SecretRedactor, which shares only the key test: it masks one value
 * at a time for display, where this scans the environment and config. Kept separate until a
 * third consumer shows what a shared contract should be.
 */
final class SecretRedactor
{
    /** Last config-key segments that name a credential. Deliberately not `key`. */
    private const array CONFIG_CREDENTIAL_KEYS = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'private_key', 'client_secret',
        'webhook_secret', 'signing_secret', 'secret_key', 'access_token', 'refresh_token', 'dsn',
    ];

    /** @var list<string>|null */
    private ?array $secretValues = null;

    public function __construct(
        private readonly ArtisanUIConfig $config,
        private readonly ?Repository $appConfig = null,
    ) {}

    public function isSecretKey(string $key): bool
    {
        $key = strtolower(ltrim($key, '-'));

        return array_any($this->config->redactionKeys(), fn (string $pattern) => Str::is(strtolower($pattern), $key));
    }

    /**
     * Mask the value of every secret-named key, recursively.
     *
     * @param array<array-key, mixed> $input
     *
     * @return array<array-key, mixed>
     */
    public function redactInput(array $input): array
    {
        $mask = $this->config->redactionMask();

        foreach ($input as $key => $value) {
            if (is_string($key) && $this->isSecretKey($key) && ! is_bool($value) && $value !== null) {
                $input[$key] = $mask;
            } elseif (is_array($value)) {
                $input[$key] = $this->redactInput($value);
            }
        }

        return $input;
    }

    /**
     * The shape the audit trail records: both sections present, secret values masked.
     *
     * @return array{arguments: array<array-key, mixed>, options: array<array-key, mixed>}
     */
    public function redactRunInput(ValidatedInput $input): array
    {
        return [
            'arguments' => $this->redactInput($input->arguments),
            'options'   => $this->redactInput($input->options),
        ];
    }

    /**
     * @param bool $truncated the text was cut at the output cap, so a secret may end part-way
     */
    public function scrub(string $text, bool $truncated = false): string
    {
        if ($text === '') {
            return $text;
        }

        $mask = $this->config->redactionMask();

        $values = $this->secretValues();

        if ($values !== []) {
            $text = str_replace($values, $mask, $text);

            // Cut at the cap, a secret's head no longer matches the whole value: mask any tail of
            // the text that is the start of one.
            if ($truncated) {
                $text = $this->maskTrailingPrefix($text, $values, $mask);
            }
        }

        // `[ \t]`, not `\s`: `\s` also matches newlines, and on output with long runs of blank
        // lines every line start then rescanned the rest of the run: quadratic, and 1 MB of it
        // held a worker for minutes after the run's own time limit had been lifted.
        $text = (string) preg_replace_callback(
            '/^([ \t]*(?:export[ \t]+)?)([A-Za-z_][A-Za-z0-9_.]*)([ \t]*[=:][ \t]*)(.+)$/m',
            fn (array $m): string => $this->isSecretKey($m[2]) ? $m[1] . $m[2] . $m[3] . $mask : $m[0],
            $text,
        );

        return (string) preg_replace(
            '~\b([a-z][a-z0-9+.\-]*://[^:/\s@]+:)([^@\s/]+)(@)~i',
            '$1' . $mask . '$3',
            $text,
        );
    }

    /**
     * Whether a configuration value is a credential, judged far more strictly than an
     * environment variable name. Stock Laravel config is full of keys that merely end in a
     * secret-looking word: `cache.stores.session.key => '_cache'`, and every facade alias under
     * `app.aliases` (`Password => Illuminate\Support\Facades\Password`). Treating those as
     * secrets masked `create_cache_table` and every `use` statement in migrate output.
     *
     * So only `app.key`, `app.previous_keys.*` and keys whose last segment is a credential
     * name count; class names never do.
     */
    private function isConfigSecret(string $path, mixed $value): bool
    {
        if (! is_string($value) || str_contains($value, '\\') || str_starts_with($path, 'app.aliases.') || str_starts_with($path, 'app.providers.')) {
            return false;
        }

        if ($path === 'app.key' || str_starts_with($path, 'app.previous_keys.')) {
            return true;
        }

        $segments = explode('.', $path);
        $last = strtolower(end($segments));

        return in_array($last, self::CONFIG_CREDENTIAL_KEYS, true);
    }

    /**
     * @param list<string> $values
     */
    private function collect(array &$values, mixed $name, mixed $value, int $min): void
    {
        if (! is_string($name) || ! is_string($value) || strlen($value) < $min || ! $this->isSecretKey($name)) {
            return;
        }

        $values[] = $value;

        // APP_KEY is usually printed without its `base64:` prefix too.
        if (str_starts_with($value, 'base64:') && strlen($value) - 7 >= $min) {
            $values[] = substr($value, 7);
        }
    }

    /**
     * @param list<string> $values
     */
    private function maskTrailingPrefix(string $text, array $values, string $mask): string
    {
        foreach ($values as $value) {
            // The `base64:` form's head is a common word; its bare form (also in the list)
            // covers it. Eight characters minimum, so "data" in "database" is not a secret.
            if (str_starts_with($value, 'base64:')) {
                continue;
            }

            for ($length = min(strlen($value) - 1, strlen($text)); $length >= 8; $length--) {
                if (str_ends_with($text, substr($value, 0, $length))) {
                    return substr($text, 0, -$length) . $mask;
                }
            }
        }

        return $text;
    }

    /**
     * @return list<string> longest first, so a value containing another is replaced whole
     */
    private function secretValues(): array
    {
        if ($this->secretValues !== null) {
            return $this->secretValues;
        }

        $min = $this->config->minScrubLength();
        $values = [];

        foreach ([...$_SERVER, ...$_ENV, ...getenv()] as $name => $value) {
            $this->collect($values, $name, $value, $min);
        }

        // The loaded configuration too: under `config:cache` the .env file is never read into
        // the environment, so in production the secrets live only in config (app.key,
        // database.connections.*.password, services.*.secret ...). A config key is judged by
        // its last segment.
        foreach (Arr::dot($this->appConfig?->all() ?? []) as $key => $value) {
            if ($this->isConfigSecret((string) $key, $value)) {
                $this->collect($values, 'secret', $value, max($min, 8));
            }
        }

        $values = array_values(array_unique($values));
        usort($values, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $this->secretValues = $values;
    }
}
