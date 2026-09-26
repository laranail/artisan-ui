<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduledCommands(): array
{
    return array_map(static fn (Event $event): string => (string) $event->command, app(Schedule::class)->events());
}

it('schedules pruning of the run table with the database driver', function (): void {
    config()->set('laranail.artisan-ui.audit.driver', 'database');

    expect(implode("\n", scheduledCommands()))->toContain('model:prune')->toContain('CommandRun');
})->skip(fn (): bool => ! method_exists(Schedule::class, 'events'), 'schedule introspection unavailable');

it('schedules nothing with the log driver', function (): void {
    expect(implode("\n", scheduledCommands()))->not->toContain('CommandRun');
});
