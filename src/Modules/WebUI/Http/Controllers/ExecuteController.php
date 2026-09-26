<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandBusy;
use Simtabi\Laranail\ArtisanUI\Core\Policy\CommandAuthorizer;
use Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandRegistry;
use Simtabi\Laranail\ArtisanUI\Core\Execution\CommandExecutor;
use Simtabi\Laranail\ArtisanUI\Core\Validation\InputValidator;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\AccessGuard;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandNotRunnable;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\RunPresenter;
use Simtabi\Laranail\Package\Tools\Http\Controllers\WebController;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\InvalidCommandInput;
use Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\DestructiveConfirmation;

/**
 * The one endpoint that runs anything.
 *
 * In order: the command must be listed; the input must validate; the user must be allowed to
 * run THIS command with THIS input; a destructive command must be confirmed; then it runs,
 * under a lock, audited. Every earlier step fails without the later ones happening. POST only,
 * behind CSRF and a per-user rate limit, which upstream's GET-less but ungated endpoint and
 * dev-arindam-roy's unauthenticated GET did not have.
 */
final class ExecuteController extends WebController
{
    public function __invoke(
        Request $request,
        string $command,
        CommandRegistry $registry,
        InputValidator $validator,
        CommandAuthorizer $authorizer,
        RiskClassifier $risk,
        DestructiveConfirmation $confirmation,
        CommandExecutor $executor,
        RunPresenter $presenter,
        AccessGuard $guard,
    ): JsonResponse {
        $definition = $registry->find($command) ?? abort(404);

        /** @var Authenticatable $user */
        $user = $request->user();

        try {
            $input = $validator->validate($definition, $request->input('arguments'), $request->input('options'));
        } catch (InvalidCommandInput $invalid) {
            return new JsonResponse([
                'message' => __('laranail/artisan-ui::messages.invalid_input'),
                'errors'  => $invalid->errors,
            ], 422);
        }

        $decision = $authorizer->inspect($user, $definition, $input);

        if (! $decision->allowed) {
            $guard->deny($request, $decision, $definition->name);
        }

        if ($risk->classify($definition->name)->requiresConfirmation()) {
            $refusal = $confirmation->check($request, $definition);

            if ($refusal instanceof JsonResponse) {
                return $refusal;
            }
        }

        try {
            $execution = $executor->execute($definition, $input, $user, $request->ip());
        } catch (CommandBusy) {
            return new JsonResponse(['message' => __('laranail/artisan-ui::messages.busy', ['command' => $definition->name])], 409);
        } catch (CommandNotRunnable) {
            abort(404);
        }

        return new JsonResponse($presenter->toArray($execution));
    }
}
