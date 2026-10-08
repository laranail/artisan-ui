<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Simtabi\Laranail\ArtisanUI\Enums\Ability;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandFailed;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuted;
use Simtabi\Laranail\ArtisanUI\Core\Events\CommandExecuting;
use Simtabi\Laranail\Package\Tools\Services\Boot\BootReport;
use Simtabi\Laranail\ArtisanUI\Core\Exceptions\CommandRunFailed;

function runCommand(string $command, array $body = []): TestResponse
{
    return test()->postJson(route('laranail-artisan-ui.execute', $command), $body);
}

function outputText(TestResponse $response): string
{
    return implode('', array_column((array) $response->json('output'), 'text'));
}

beforeEach(function (): void {
    allowEverything();
    $this->actingAs($this->makeUser());
});

it('runs a command with its arguments and options and returns the output', function (): void {
    $response = runCommand('lau-fixture:echo', [
        'arguments' => ['text' => 'hello'],
        'options'   => ['shout' => '1', 'tag' => ['a', 'b']],
    ]);

    $response->assertOk()->assertJson([
        'command'   => 'lau-fixture:echo',
        'status'    => 'succeeded',
        'success'   => true,
        'exit_code' => 0,
        'error'     => null,
    ]);

    expect(outputText($response))->toContain('HELLO')->toContain('tag:a')->toContain('tag:b')
        ->and($response->json('run_id'))->toBeString()->toHaveLength(26);
});

it('runs a real framework command', function (): void {
    runCommand('about', ['options' => ['only' => 'environment']])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('is POST only', function (): void {
    $this->get('/artisan/commands/about/run')->assertMethodNotAllowed();
});

it('refuses invalid input with field errors and runs nothing', function (): void {
    Event::fake([CommandExecuting::class]);

    runCommand('lau-fixture:echo', ['options' => ['env' => 'production', 'nope' => '1']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['arguments.text', 'options.env', 'options.nope']);

    Event::assertNotDispatched(CommandExecuting::class);
});

it('asks the Run ability about the specific command and input', function (): void {
    Gate::define(Ability::Run->value, static fn ($user, $command, array $input): bool => $command->name === 'lau-fixture:echo'
        && ($input['arguments']['text'] ?? null) !== 'forbidden-word');

    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'ok']])->assertOk();
    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'forbidden-word']])->assertForbidden();
    runCommand('lau-fixture:secret')->assertForbidden();
});

it('404s unlisted, hidden and forbidden commands at the run endpoint too', function (string $command): void {
    runCommand($command)->assertNotFound();
})->with(['serve', 'tinker', 'lau-fixture:hidden', 'does-not-exist']);

it('refuses writes-files commands outside their environments', function (): void {
    runCommand('lau-fixture:make')->assertForbidden();

    config()->set('laranail.artisan-ui.risk.writes_files_environments', ['testing']);

    runCommand('lau-fixture:make')->assertOk();
});

it('runs non-interactively, so a prompting command answers its default instead of hanging', function (): void {
    runCommand('lau-fixture:ask')
        ->assertOk()
        ->assertJson(['status' => 'failed', 'exit_code' => 7, 'success' => false]);
});

it('never shows exception detail, reports it with its cause, and records the run as errored', function (): void {
    Exceptions::fake();

    $response = runCommand('lau-fixture:throw')->assertOk()->assertJson(['status' => 'errored', 'success' => false]);

    expect($response->json('error'))->toContain($response->json('run_id'))
        ->not->toContain('redaction-canary-not-a-secret')
        ->not->toContain('internal detail');

    Exceptions::assertReported(static fn (CommandRunFailed $e): bool => $e->command === 'lau-fixture:throw'
        && $e->getPrevious() instanceof RuntimeException
        && $e->context()['decision'] === 'recorded-errored');
});

it('redacts secrets and never returns markup', function (): void {
    $_ENV['LAU_FIXTURE_PASSWORD'] = 'super-secret-value';

    $response = runCommand('lau-fixture:secret')->assertOk();
    $raw = (string) $response->getContent();

    unset($_ENV['LAU_FIXTURE_PASSWORD']);

    expect(outputText($response))->not->toContain('super-secret-value')->toContain('••••••••')
        ->toContain('<script>alert(1)</script>');

    // The markup is text inside a JSON string, never HTML the client would parse.
    expect($raw)->not->toContain('<span')
        ->and(collect($response->json('output'))->pluck('classes')->filter()->values()->all())->toBe(['lau-fg-red']);
});

it('refuses a concurrent run of the same command', function (): void {
    $lock = Cache::lock('laranail-artisan-ui:run:lau-fixture:echo', 60);
    $lock->get();

    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'x']])->assertStatus(409);

    $lock->release();

    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'x']])->assertOk();
});

it('dispatches lifecycle events with the redacted input', function (): void {
    Event::fake([CommandExecuting::class, CommandExecuted::class, CommandFailed::class]);

    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'x']]);
    runCommand('lau-fixture:ask');

    Event::assertDispatched(CommandExecuting::class, 2);
    Event::assertDispatched(CommandExecuted::class, static fn (CommandExecuted $e): bool => $e->context->command->name === 'lau-fixture:echo');
    Event::assertDispatched(CommandFailed::class, static fn (CommandFailed $e): bool => $e->result->exitCode === 7);
});

it('reruns cleanly with the same input (pabloleone issue #2)', function (): void {
    $body = ['arguments' => ['text' => 'again'], 'options' => ['tag' => ['x']]];

    runCommand('lau-fixture:echo', $body)->assertOk()->assertJson(['success' => true]);
    runCommand('lau-fixture:echo', $body)->assertOk()->assertJson(['success' => true]);
});

it('leaves boot health clean after a normal run (the CI degraded-state gate)', function (): void {
    runCommand('lau-fixture:echo', ['arguments' => ['text' => 'x']])->assertOk();

    $report = app(BootReport::class);

    expect($report->isHealthy())->toBeTrue('degraded: ' . json_encode($report->degraded()));
});
