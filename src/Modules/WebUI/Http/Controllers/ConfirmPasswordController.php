<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Simtabi\Laranail\ArtisanUI\Core\Policy\Decision;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\AccessGuard;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\DestructiveConfirmation;

/**
 * Re-authenticates the signed-in user before a destructive command.
 *
 * Checked against the guard's own user provider (`validateCredentials`), so it works with
 * whatever user model and hasher the application uses, and needs no `password.confirm`
 * route in the application. Throttled to five attempts a minute per user.
 */
final class ConfirmPasswordController extends WebController
{
    public function __invoke(
        Request $request,
        Auth $auth,
        ArtisanUIConfig $config,
        DestructiveConfirmation $confirmation,
        AccessGuard $access,
    ): JsonResponse {
        /** @var Authenticatable $user */
        $user = $request->user();
        $password = $request->input('password');

        $guard = $auth->guard($config->guard());
        // A custom guard may have no user provider; then nothing can check the password, and a
        // destructive command stays unavailable rather than failing with a fatal (AUI-15).
        $provider = method_exists($guard, 'getProvider') ? $guard->getProvider() : null;

        if (! $provider instanceof UserProvider) {
            return $this->refuse($request, $access, 'password_unavailable');
        }

        if (! is_string($password) || $password === '' || ! $provider->validateCredentials($user, ['password' => $password])) {
            return $this->refuse($request, $access, 'password_incorrect');
        }

        $confirmation->markConfirmed($request);

        return new JsonResponse(['confirmed' => true]);
    }

    /**
     * Answer 422 with the message as a field error, and record the refusal the way every other
     * denial is recorded: a log line and an AccessDenied event with the reason `password`.
     */
    private function refuse(Request $request, AccessGuard $access, string $message): JsonResponse
    {
        $access->record($request, Decision::deny('password', 422));

        $text = __('laranail/artisan-ui::messages.' . $message);

        return new JsonResponse(['message' => $text, 'errors' => ['password' => [$text]]], 422);
    }
}
