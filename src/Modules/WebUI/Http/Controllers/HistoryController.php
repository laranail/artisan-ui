<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Auth\Access\Gate;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Enums\RunStatus;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;
use Simtabi\Laranail\ArtisanUI\Core\Policy\Decision;
use Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun;
use Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\AccessGuard;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;

/**
 * Recorded runs, newest first, filterable by command and status. Only exists with the
 * database audit driver, and only for users the ViewHistory ability allows. Rerun links to
 * the command form pre-filled with the recorded (redacted) input: a masked secret has to be
 * typed again, by design.
 */
final class HistoryController extends WebController
{
    public function __invoke(
        Request $request,
        ArtisanUIConfig $config,
        Gate $gate,
        AccessGuard $guard,
        AnsiFormatter $ansi,
        ThemeView $view,
    ): View {
        if ($config->auditDriver() !== AuditDriver::Database) {
            abort(404);
        }

        if (! $gate->forUser($request->user())->allows(Ability::ViewHistory->value)) {
            $guard->deny($request, Decision::deny('gate'));
        }

        $command = $request->query('command');
        $status = RunStatus::tryFrom((string) $request->query('status', ''));

        $runs = CommandRun::query()
            ->when(is_string($command) && $command !== '', static fn ($query) => $query->where('command', $command))
            ->when($status instanceof RunStatus, static fn ($query) => $query->where('status', $status?->value))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return $view->make('history', [
            'runs'     => $runs,
            'ansi'     => $ansi,
            'command'  => is_string($command) ? $command : '',
            'status'   => $status,
            'statuses' => RunStatus::cases(),
        ]);
    }
}
