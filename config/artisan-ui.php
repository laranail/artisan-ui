<?php

declare(strict_types=1);

use Simtabi\Laranail\ArtisanUI\Enums\AssetMode;
use Simtabi\Laranail\ArtisanUI\Enums\AuditDriver;

/*
 * laranail/artisan-ui
 *
 * Read at `config('laranail.artisan-ui.*')`; publish with
 * `php artisan vendor:publish --tag=laranail::artisan-ui-config`, which writes
 * config/laranail/artisan-ui.php.
 *
 * Every default below fails closed. The panel is off, only reachable in the
 * `local` environment, and every Gate ability denies until the application
 * defines it. Turning it on is a deliberate act in three places, not one.
 */
return [

    /*
     * Master switch. While false the routes are not registered at all, and a
     * cached route file that still carries them answers 404.
     */
    'enabled' => (bool) env('LARANAIL_ARTISAN_UI_ENABLED', false),

    /*
     * Where the panel lives. `domain` null means any host.
     */
    'domain' => env('LARANAIL_ARTISAN_UI_DOMAIN'),

    'path' => env('LARANAIL_ARTISAN_UI_PATH', 'artisan'),

    /*
     * Middleware applied BEFORE the package's own guards. The access guard and
     * the security headers are always appended by the package, so removing
     * something from this list can never remove them.
     *
     * The panel needs a session, so keep `web` (or a group containing it).
     */
    'middleware' => ['web'],

    /*
     * The auth guard that must hold a user. Null uses the application default.
     * It must be a session-based guard; `laranail::artisan-ui.doctor` warns
     * otherwise.
     */
    'guard' => env('LARANAIL_ARTISAN_UI_GUARD'),

    /*
     * Environments in which the panel answers at all. The Gate still has to
     * allow the user as well; this is a second, independent lock.
     *
     * `['*']` allows every environment. Doctor fails if `production` or `*` is listed.
     */
    'environments' => ['local'],

    /*
     * Optional IP allowlist. Empty allows any address. Accepts single addresses
     * and CIDR ranges, IPv4 and IPv6.
     */
    'allowed_ips' => [],

    /*
     * Log channel for denied access attempts. Null uses the default channel.
     */
    'log_channel' => env('LARANAIL_ARTISAN_UI_LOG_CHANNEL'),

    /*
     * Which commands are listed and runnable.
     *
     * Patterns use `Str::is()` wildcards. `migrate:*` also matches the bare
     * `migrate` command, so a namespace pattern covers its root command too.
     * The deny list always wins over the allow list.
     */
    'commands' => [
        // null lists every command; a list restricts the panel to exactly those.
        'allow' => null,

        'deny' => [],

        // Hidden commands are hidden for a reason; leave false unless you know why.
        'include_hidden' => false,
    ],

    /*
     * Risk classification. The first list that matches decides, in this order:
     * safe → forbidden → destructive → writes_files. Anything unmatched is safe.
     *
     * - forbidden:    never listed and never runnable. Long-running processes
     *                 and interactive shells, which would hold a PHP worker.
     * - destructive:  runnable only after typing the command name and
     *                 re-entering the password.
     * - writes_files: runnable only in `writes_files_environments`.
     *
     * Entries here are merged over the package defaults, which are listed in
     * docs/tools/risk-levels.md.
     */
    'risk' => [
        'safe'                      => [],
        'forbidden'                 => [],
        'destructive'               => [],
        'writes_files'              => [],
        'writes_files_environments' => ['local'],
    ],

    /*
     * Re-authentication before a destructive command. The confirmation is
     * shared with Laravel's own `password.confirm` middleware through the
     * `auth.password_confirmed_at` session key.
     */
    'confirmation' => [
        'require_password' => true,

        // Seconds a password confirmation stays valid. Null follows auth.password_timeout.
        'password_timeout' => null,
    ],

    /*
     * Bounds on a single run.
     */
    'limits' => [
        'max_value_length' => 1_000,
        'max_array_items'  => 50,
        'max_output_bytes' => 1_000_000,

        // Seconds. Passed to set_time_limit() for the duration of the run.
        'time_limit' => 120,

        // Seconds a run's lock lives if nothing releases it. Never less than time_limit + 10.
        // A run killed by the time limit or a fatal error releases its lock from a shutdown
        // handler; this is only the backstop.
        'lock_seconds' => 300,
    ],

    /*
     * Executions per user per minute.
     */
    'rate_limit' => [
        'per_minute' => 30,
    ],

    /*
     * The audit trail.
     *
     * - log:      one structured line per run on `channel`.
     * - database: a row per run in `laranail_artisan_ui_runs`, which also
     *             powers the History screen and rerun. Publish and run the
     *             migration first.
     * - none:     nothing is recorded.
     *
     * Point `connection` somewhere `migrate:fresh` does not reach if you run
     * database commands from the panel; the recorder falls back to the log
     * when its table disappears mid-run, but a separate connection keeps the
     * history intact.
     */
    'audit' => [
        'driver'         => env('LARANAIL_ARTISAN_UI_AUDIT', AuditDriver::Log->value),
        'channel'        => env('LARANAIL_ARTISAN_UI_AUDIT_CHANNEL'),
        'connection'     => env('LARANAIL_ARTISAN_UI_AUDIT_CONNECTION'),
        'retention_days' => 90,

        // Register `model:prune` for the run table on the scheduler (daily). Turn off to
        // schedule it yourself.
        'schedule_prune' => true,

        'store_output' => true,
    ],

    /*
     * Secret redaction, applied to output and to recorded input.
     *
     * `keys` are wildcard patterns matched case-insensitively against option
     * and environment variable names. The value of every matching environment
     * variable is also scrubbed from output wherever it appears.
     */
    'redaction' => [
        'keys' => [
            '*password*',
            '*secret*',
            '*token*',
            '*_key',
            'key',
            '*private*',
            '*dsn*',
            '*credential*',
        ],
        'mask' => '••••••••',

        // Values shorter than this are not scrubbed from output, so a four
        // character password does not blank every occurrence of "test".
        'min_scrub_length' => 6,
    ],

    /*
     * Output decorators: command pattern => class implementing
     * Simtabi\Laranail\ArtisanUI\Core\Contracts\OutputDecorator.
     * They run before redaction, so a decorator cannot leak a secret.
     */
    'decorators' => [],

    /*
     * Quick actions shown on the home screen. They only pre-fill a command
     * form; running one goes through exactly the same checks as any other run.
     */
    'presets' => [
        'enabled' => true,

        // Extra groups: key => ['label' => '...', 'actions' => [[
        //     'label' => '...', 'command' => '...',
        //     'arguments' => [], 'options' => [], 'description' => '...',
        // ]]]
        'groups' => [],
    ],

    /*
     * The view directory under resources/views/themes. Publish the views and
     * add a directory to create your own.
     */
    'theme' => 'default',

    /*
     * How the stylesheet and script reach the browser.
     *
     * - route:     served by the package from a content-hashed, immutable URL.
     * - published: served from public/vendor/artisan-ui after
     *              `vendor:publish --tag=laranail::artisan-ui-assets`.
     */
    'assets' => [
        'mode'  => env('LARANAIL_ARTISAN_UI_ASSETS', AssetMode::Route->value),
        'route' => 'vendor/laranail-artisan-ui',
    ],
];
