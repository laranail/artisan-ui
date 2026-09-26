<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Presets\PresetCatalog;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView;
use Simtabi\Laranail\ArtisanUI\Core\Environment\EnvironmentInfo;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;

final class HomeController extends WebController
{
    public function __invoke(
        Request $request,
        CommandRegistry $registry,
        PresetCatalog $presets,
        RiskClassifier $risk,
        EnvironmentInfo $environment,
        Gate $gate,
        ThemeView $view,
    ): View {
        $gate = $gate->forUser($request->user());

        return $view->make('home', [
            'groups'      => $registry->grouped(),
            'risk'        => $risk,
            'presets'     => $presets->groups(),
            'environment' => $gate->allows(Ability::ViewEnvironment->value) ? $environment->snapshot() : null,
        ]);
    }
}
