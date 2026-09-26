<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Core\Policy;

use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\ArtisanUI\Enums\CommandRisk;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Validation\ValidatedInput;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;

/**
 * Whether a given user may run a given command with a given input.
 *
 * This replaces the forks' approaches outright: no stored credentials, no environment-only
 * check. The decision is the application's, through the Run ability, which receives the
 * command and the validated input so it can scope by user AND by command. Around it sit the
 * checks the application cannot switch off by accident: the command policy, the Forbidden
 * risk class and the writes-files environment rule.
 *
 * Destructive confirmation is not here: it depends on the session, which belongs to the
 * transport. See Modules\WebUI\Support\DestructiveConfirmation.
 */
final readonly class CommandAuthorizer
{
    public function __construct(
        private Gate $gate,
        private CommandPolicy $policy,
        private RiskClassifier $risk,
        private ArtisanUIConfig $config,
        private Application $app,
    ) {}

    public function inspect(Authenticatable $user, CommandDefinition $command, ValidatedInput $input): Decision
    {
        if (! $this->policy->permits($command->name)) {
            return Decision::deny('policy', 404);
        }

        $risk = $this->risk->classify($command->name);

        if (! $risk->isRunnable()) {
            return Decision::deny('forbidden', 404);
        }

        if ($risk === CommandRisk::WritesFiles
            && ! PanelAccess::environmentMatches($this->app, $this->config->writesFilesEnvironments())) {
            return Decision::deny('writes_files_environment');
        }

        if (! $this->gate->forUser($user)->allows(Ability::Run->value, [$command, $input->toArray()])) {
            return Decision::deny('gate');
        }

        return Decision::allow();
    }
}
