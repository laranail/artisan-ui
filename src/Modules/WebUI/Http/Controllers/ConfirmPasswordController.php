<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;
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
    ): JsonResponse {
        /** @var Authenticatable $user */
        $user = $request->user();
        $password = $request->input('password');

        $provider = $auth->guard($config->guard())->getProvider();

        if (! is_string($password) || $password === '' || ! $provider->validateCredentials($user, ['password' => $password])) {
            return new JsonResponse([
                'message' => __('laranail/artisan-ui::messages.password_incorrect'),
                'errors'  => ['password' => [__('laranail/artisan-ui::messages.password_incorrect')]],
            ], 422);
        }

        $confirmation->markConfirmed($request);

        return new JsonResponse(['confirmed' => true]);
    }
}
