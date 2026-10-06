<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Support\CommandPattern;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Classifies a command by name into a CommandRisk.
 *
 * The lists below are the package defaults; the `risk.*` configuration is merged over them,
 * never replacing them, so upgrading the package can add a newly dangerous framework command
 * without an application having to notice. The first list that matches decides, in the order
 * safe → forbidden → destructive → writes_files, and anything unmatched is Safe.
 */
final readonly class RiskClassifier
{
    /**
     * Exceptions carved out of a broader pattern below. `migrate:status` only reads.
     */
    public const array SAFE = [
        'migrate:status',
    ];

    /**
     * Long-running processes and interactive shells. Run from a web request, each one holds
     * a PHP worker until the time limit kills it, and several never exit at all.
     *
     * Also `config:show`: it prints configuration values, credentials included, in a dotted
     * table the redactor cannot reliably recognise. An application that wants it can list it
     * under `risk.safe`, which is checked first.
     */
    public const array FORBIDDEN = [
        'config:show',
        // Framework internals, hidden for a reason: `invoke-serialized-closure` unserialize()s
        // its argument, which from a web form is object injection. `schedule:finish` is the
        // scheduler's own bookkeeping.
        'invoke-serialized-closure',
        'schedule:finish',
        'serve',
        'tinker',
        'db',
        'dev',
        'pail',
        'queue:work',
        'queue:listen',
        'schedule:work',
        'octane:*',
        'horizon',
        'reverb:start',
        'sail:*',
        'pulse:check',
        'pulse:work',
        'nightwatch:agent',
    ];

    /**
     * Commands that destroy data, rotate secrets or take the application down.
     *
     * `down` is here because running it from the panel locks the panel out too, including
     * for whoever then needs to run `up`, unless the panel's path is exempt from maintenance
     * mode or `down --secret` is used. `key:generate` rotates APP_KEY, which signs everyone
     * out and makes previously encrypted data unreadable.
     */
    public const array DESTRUCTIVE = [
        'migrate:*',
        'db:wipe',
        'db:seed',
        'key:generate',
        'down',
        'env:decrypt',
        'queue:clear',
        'queue:flush',
        'queue:forget',
        'queue:prune-batches',
        'queue:prune-failed',
        'model:prune',
        'auth:clear-resets',
        'storage:unlink',
        'schedule:run',
        // Runs any scheduled task the operator picks, whatever that task does.
        'schedule:test',
        // Re-dispatches failed jobs, which may have failed for a reason.
        'queue:retry',
        'queue:retry-batch',
        // Rewrites .env.encrypted; with --force, over an existing one.
        'env:encrypt',
        'schedule:interrupt',
        'horizon:terminate',
        'horizon:clear',
        'horizon:purge',
        // This package's own maintenance command. It deletes files under storage, and its
        // `db` action runs `migrate:fresh`; one name covers every action, so the class is
        // set by the worst of them, as `migrate:*` is.
        'laranail::artisan-ui.tidy',
    ];

    /**
     * Commands that change files in the application.
     */
    public const array WRITES_FILES = [
        'make:*',
        'vendor:publish',
        'storage:link',
        'lang:publish',
        'stub:publish',
        'config:publish',
        'install:*',
        'ide-helper:*',
    ];

    public function __construct(
        private ArtisanUIConfig $config,
    ) {}

    public function classify(string $command): CommandRisk
    {
        return match (true) {
            CommandPattern::any($this->patterns('safe', self::SAFE), $command)                 => CommandRisk::Safe,
            CommandPattern::any($this->patterns('forbidden', self::FORBIDDEN), $command)       => CommandRisk::Forbidden,
            CommandPattern::any($this->patterns('destructive', self::DESTRUCTIVE), $command)   => CommandRisk::Destructive,
            CommandPattern::any($this->patterns('writes_files', self::WRITES_FILES), $command) => CommandRisk::WritesFiles,
            default                                                                            => CommandRisk::Safe,
        };
    }

    /**
     * @param list<string> $defaults
     *
     * @return list<string>
     */
    private function patterns(string $class, array $defaults): array
    {
        return array_values(array_unique([...$defaults, ...$this->config->riskPatterns($class)]));
    }
}
