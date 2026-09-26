<?php

declare(strict_types=1);

namespace Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\LogManager;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\ArtisanUI\Core\Policy\Decision;
use Illuminate\Http\Exceptions\HttpResponseException;
use Simtabi\Laranail\ArtisanUI\Core\Events\AccessDenied;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig;

/**
 * Turns a denial into a log line, an AccessDenied event, and an HTTP error.
 *
 * The reason goes to the log and the event, never to the response body: the person who was
 * refused learns only that they were (failure-handling standard, rule 11). A disabled panel
 * answers 404 and is not logged, since there is nothing there to be refused.
 */
final readonly class AccessGuard
{
    public function __construct(
        private LogManager $log,
        private Dispatcher $events,
        private ArtisanUIConfig $config,
        private ThemeView $views,
    ) {}

    public function deny(Request $request, Decision $decision, ?string $command = null): never
    {
        if ($decision->reason !== 'disabled') {
            $userId = $request->user()?->getAuthIdentifier();
            $userId = is_scalar($userId) ? (string) $userId : null;

            try {
                $channel = $this->config->logChannel();
                ($channel === null ? $this->log->driver() : $this->log->channel($channel))
                    ->warning('laranail/artisan-ui: access denied', [
                        'reason'   => $decision->reason,
                        'status'   => $decision->status,
                        'ip'       => $request->ip(),
                        'path'     => $request->path(),
                        'user'     => $userId,
                        'command'  => $command,
                        'decision' => 'refused',
                    ]);
            } catch (Throwable $e) {
                error_log('laranail/artisan-ui: access-denied log write failed: ' . $e->getMessage());
            }

            $this->events->dispatch(new AccessDenied(
                (string) $decision->reason,
                $request->ip(),
                $request->path(),
                $userId,
                $command,
            ));
        }

        // JSON clients get the bare status. A browser gets the panel's own page: the framework's
        // default error views style themselves inline, which the panel's CSP (applied to denials
        // too) refuses, and the reason is never part of either.
        // Disabled means absent: the framework's plain 404, never the panel's own page and
        // stylesheet URL, which would fingerprint a panel that is meant not to be there.
        if ($request->expectsJson() || $decision->reason === 'disabled') {
            throw new HttpException($decision->status);
        }

        throw new HttpResponseException(new Response(
            $this->views->make('denied', [
                'status'  => $decision->status,
                'message' => __('laranail/artisan-ui::messages.denied.' . ($decision->status === 404 ? 'not_found' : 'forbidden')),
            ])->render(),
            $decision->status,
        ));
    }
}
