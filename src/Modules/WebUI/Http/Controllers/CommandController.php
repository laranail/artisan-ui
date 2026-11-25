<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Prefill;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\DestructiveConfirmation;

final class CommandController extends WebController
{
    public function __invoke(
        Request $request,
        string $command,
        CommandRegistry $registry,
        RiskClassifier $risk,
        DestructiveConfirmation $confirmation,
        ArtisanUIConfig $config,
        ThemeView $view,
        Gate $gate,
    ): View {
        $definition = $registry->find($command) ?? abort(404);

        return $view->make('command', [
            'command'           => $definition,
            'risk'              => $risk->classify($definition->name),
            'prefill'           => $this->prefill($request, $definition, $config, $gate),
            'passwordConfirmed' => $confirmation->recentlyConfirmed($request),
        ]);
    }

    /**
     * `?rerun=<run id>` fills the form from the recorded run, for whoever may view history, and
     * only for the command that run was of; otherwise the query-string prefill applies, as
     * before, so existing links keep working.
     *
     * @return array<string, mixed>
     */
    private function prefill(Request $request, CommandDefinition $definition, ArtisanUIConfig $config, Gate $gate): array
    {
        $rerun = $request->query('rerun');

        if (is_string($rerun) && $rerun !== '' && $config->auditDriver() === AuditDriver::Database && $gate->allows(Ability::ViewHistory->value)) {
            $run = CommandRun::query()->whereKey($rerun)->where('command', $definition->name)->first();

            if ($run instanceof CommandRun) {
                return Prefill::fromRun($run->arguments, $run->options, $definition, $config->redactionMask());
            }
        }

        return Prefill::fromRequest($request, $definition, $config->redactionMask());
    }
}
