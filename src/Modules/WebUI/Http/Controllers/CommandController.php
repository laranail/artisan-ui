<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Prefill;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView;
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
    ): View {
        $definition = $registry->find($command) ?? abort(404);

        return $view->make('command', [
            'command'           => $definition,
            'risk'              => $risk->classify($definition->name),
            'prefill'           => Prefill::fromRequest($request, $definition, $config->redactionMask()),
            'passwordConfirmed' => $confirmation->recentlyConfirmed($request),
        ]);
    }
}
