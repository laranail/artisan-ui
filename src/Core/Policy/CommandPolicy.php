<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

use Simtabi\Laranail\ArtisanUI\Core\Support\CommandPattern;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Which commands the panel exposes at all.
 *
 * This is the allowlist/denylist from upstream PR #9 and pabloleone's `excluded` list, with
 * the matching fixed (see CommandPattern) and Forbidden-risk commands removed outright. It is
 * applied to listing AND execution through CommandRegistry, so a command that is not listed
 * cannot be run by guessing its URL.
 */
final readonly class CommandPolicy
{
    public function __construct(
        private ArtisanUIConfig $config,
        private RiskClassifier $risk,
    ) {}

    public function isListed(string $command, bool $hidden = false): bool
    {
        if ($hidden && ! $this->config->includeHidden()) {
            return false;
        }

        return $this->permits($command) && $this->risk->classify($command)->isRunnable();
    }

    /**
     * The allow and deny lists alone, without hidden-ness or risk. Deny always wins.
     */
    public function permits(string $command): bool
    {
        if (CommandPattern::any($this->config->denyPatterns(), $command)) {
            return false;
        }

        $allow = $this->config->allowPatterns();

        return $allow === null || CommandPattern::any($allow, $command);
    }
}
