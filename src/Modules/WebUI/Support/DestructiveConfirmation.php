<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;

/**
 * The two things a destructive command needs before it runs: its name typed back, and a
 * password confirmation within the timeout.
 *
 * The confirmation timestamp is the `auth.password_confirmed_at` session key Laravel's own
 * `password.confirm` middleware writes, so a confirmation made anywhere in the application
 * counts here and one made here counts there.
 */
final readonly class DestructiveConfirmation
{
    public const string SESSION_KEY = 'auth.password_confirmed_at';

    public function __construct(
        private ArtisanUIConfig $config,
    ) {}

    /** Null when the run may go ahead, otherwise the response refusing it. */
    public function check(Request $request, CommandDefinition $command): ?JsonResponse
    {
        if ($request->input('confirm') !== $command->name) {
            return new JsonResponse([
                'message' => __('laranail/artisan-ui::messages.confirm_mismatch', ['command' => $command->name]),
                'errors'  => ['confirm' => [__('laranail/artisan-ui::messages.confirm_mismatch', ['command' => $command->name])]],
            ], 422);
        }

        if ($this->config->requiresPassword() && ! $this->recentlyConfirmed($request)) {
            FailurePolicy::warn('laranail/artisan-ui:confirm.password', [
                'command'  => $command->name,
                'expected' => 'a password confirmation within ' . $this->config->passwordTimeout() . 's',
                'actual'   => 'none, or expired',
                'decision' => 'asked to confirm',
            ]);

            return new JsonResponse([
                'message'  => __('laranail/artisan-ui::messages.password_required'),
                'requires' => 'password',
            ], 423);
        }

        return null;
    }

    public function recentlyConfirmed(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        $confirmedAt = $request->session()->get(self::SESSION_KEY);

        return is_numeric($confirmedAt)
            && Date::now()->getTimestamp() - (int) $confirmedAt < $this->config->passwordTimeout();
    }

    public function markConfirmed(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, Date::now()->getTimestamp());
    }
}
