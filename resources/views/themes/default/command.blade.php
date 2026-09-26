@extends(app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class)->resolve('layout'))

@php
    /** @var \Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition $command */
    /** @var \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk $risk */
    /** @var array<string, string|list<string>> $prefill */
    $theme = app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class);
    $destructive = $risk === \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk::Destructive;
@endphp

@section('title', $command->name)

@section('content')
    <p class="mb-4 text-sm"><a href="{{ route('laranail-artisan-ui.home') }}#group-{{ $command->namespace() ?? 'general' }}" class="text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400">← {{ __('laranail/artisan-ui::messages.back') }}</a></p>

    <header class="mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-semibold">{{ $command->name }}</h1>
            @include($theme->resolve('partials.risk-badge'), ['risk' => $risk])
        </div>
        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $command->description }}</p>
    </header>

    <details class="mb-6 rounded-md border border-zinc-200 bg-white text-sm dark:border-zinc-800 dark:bg-zinc-900">
        <summary class="cursor-pointer px-4 py-2 font-medium">{{ __('laranail/artisan-ui::messages.usage') }}</summary>
        <div class="space-y-3 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <pre class="overflow-x-auto font-mono text-xs">{{ $command->synopsis }}</pre>
            @if ($command->help !== '')
                <pre class="overflow-x-auto whitespace-pre-wrap font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $command->help }}</pre>
            @endif
        </div>
    </details>

    <form
        method="post"
        action="{{ route('laranail-artisan-ui.execute', $command->name) }}"
        data-lau-run
        data-command="{{ $command->name }}"
        data-risk="{{ $risk->value }}"
        data-confirm-url="{{ route('laranail-artisan-ui.confirm-password') }}"
        data-password-confirmed="{{ $passwordConfirmed ? '1' : '0' }}"
        class="space-y-6"
        novalidate
    >
        @csrf

        {{-- Upstream PR #2's intent, without its bug: open when there are required arguments. --}}
        <details class="rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" @if ($command->hasRequiredArguments() || collect($prefill)->keys()->contains(fn ($k) => str_starts_with($k, 'arguments.'))) open @endif>
            <summary class="cursor-pointer px-4 py-2 font-medium">{{ __('laranail/artisan-ui::messages.arguments') }} ({{ count($command->arguments) }})</summary>
            <div class="space-y-4 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                @forelse ($command->arguments as $field)
                    @include($theme->resolve('partials.field'), ['field' => $field, 'prefill' => $prefill])
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.none') }}</p>
                @endforelse
            </div>
        </details>

        <details class="rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" @if (collect($prefill)->keys()->contains(fn ($k) => str_starts_with($k, 'options.'))) open @endif>
            <summary class="cursor-pointer px-4 py-2 font-medium">{{ __('laranail/artisan-ui::messages.options') }} ({{ count($command->options) }})</summary>
            <div class="space-y-4 border-t border-zinc-100 px-4 py-4 dark:border-zinc-800">
                @forelse ($command->options as $field)
                    @include($theme->resolve('partials.field'), ['field' => $field, 'prefill' => $prefill])
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.none') }}</p>
                @endforelse
            </div>
        </details>

        @if ($risk === \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk::WritesFiles)
            <p class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">{{ __('laranail/artisan-ui::messages.risk_notice.writes_files') }}</p>
        @endif

        @if ($destructive)
            <div class="space-y-2 rounded-md border border-red-300 bg-red-50 px-4 py-3 dark:border-red-800 dark:bg-red-950">
                <p class="text-sm text-red-900 dark:text-red-200">{{ __('laranail/artisan-ui::messages.risk_notice.destructive') }}</p>
                <label for="lau-confirm" class="block text-sm font-medium">{{ __('laranail/artisan-ui::messages.confirm_label', ['command' => $command->name]) }}</label>
                <input id="lau-confirm" type="text" name="confirm" autocomplete="off" spellcheck="false" aria-describedby="lau-confirm-error" class="w-full rounded-md border border-red-300 bg-white px-3 py-1.5 font-mono text-sm dark:border-red-800 dark:bg-zinc-900">
                <p id="lau-confirm-error" data-lau-error-for="confirm" class="text-xs text-red-600 dark:text-red-400" hidden></p>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" data-lau-run-button class="rounded-md bg-zinc-900 px-5 py-2 text-sm font-medium text-white hover:bg-zinc-700 disabled:cursor-wait disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                <span data-lau-run-label>{{ __('laranail/artisan-ui::messages.run') }}</span>
                <span data-lau-running-label hidden>{{ __('laranail/artisan-ui::messages.running') }}</span>
            </button>
            <p data-lau-status role="status" aria-live="polite" class="text-sm"></p>
        </div>
    </form>

    <section class="mt-8" data-lau-output-panel hidden aria-labelledby="lau-output-heading">
        <div class="mb-2 flex items-center justify-between">
            <h2 id="lau-output-heading" class="text-lg font-semibold">{{ __('laranail/artisan-ui::messages.output') }}</h2>
            <button type="button" data-lau-clear class="text-xs text-zinc-500 dark:text-zinc-400 underline hover:text-zinc-900 dark:hover:text-white">{{ __('laranail/artisan-ui::messages.clear') }}</button>
        </div>
        {{-- overflow-x-auto: upstream PR #7. Filled with textContent from ANSI segments, never HTML. --}}
        <pre data-lau-output class="lau-output max-h-[32rem] overflow-auto rounded-md bg-zinc-900 p-4 font-mono text-xs leading-relaxed text-zinc-100" tabindex="0"></pre>
        <p data-lau-truncated hidden class="mt-2 text-xs text-amber-700 dark:text-amber-400">{{ __('laranail/artisan-ui::messages.truncated') }}</p>
    </section>

    <dialog data-lau-password class="w-full max-w-sm rounded-lg p-0 backdrop:bg-black/50 dark:bg-zinc-900 dark:text-zinc-100" aria-labelledby="lau-password-title">
        <form method="dialog" data-lau-password-form class="space-y-3 p-5">
            <h2 id="lau-password-title" class="text-lg font-semibold">{{ __('laranail/artisan-ui::messages.password_title') }}</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.password_help') }}</p>
            <label for="lau-password" class="block text-sm font-medium">{{ __('laranail/artisan-ui::messages.password_label') }}</label>
            <input id="lau-password" type="password" name="password" autocomplete="current-password" required aria-describedby="lau-password-error" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-800">
            <p id="lau-password-error" data-lau-password-error class="text-xs text-red-600 dark:text-red-400" hidden></p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-lau-password-cancel class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700">{{ __('laranail/artisan-ui::messages.password_cancel') }}</button>
                <button type="submit" class="rounded-md bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-600">{{ __('laranail/artisan-ui::messages.password_submit') }}</button>
            </div>
        </form>
    </dialog>

    {{-- Client strings, as data rather than inline script (the CSP forbids inline script). --}}
    @php
        $clientMessages = [
            'running'         => __('laranail/artisan-ui::messages.running'),
            'exit_code'       => __('laranail/artisan-ui::messages.exit_code', ['code' => ':code']),
            'duration'        => __('laranail/artisan-ui::messages.duration', ['ms' => ':ms']),
            'invalid_input'   => __('laranail/artisan-ui::messages.invalid_input'),
            'session_expired' => __('laranail/artisan-ui::messages.session_expired'),
            'forbidden'       => __('laranail/artisan-ui::messages.forbidden'),
            'not_found'       => __('laranail/artisan-ui::messages.not_found'),
            'unexpected'      => __('laranail/artisan-ui::messages.unexpected'),
            'session_expired_pending' => __('laranail/artisan-ui::messages.session_expired_pending'),
            'session_lost'    => __('laranail/artisan-ui::messages.session_lost'),
            'password_required' => __('laranail/artisan-ui::messages.password_required'),
            'cancelled'       => __('laranail/artisan-ui::messages.cancelled'),
        ];
    @endphp
    <script type="application/json" data-lau-messages>{!! json_encode($clientMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endsection
