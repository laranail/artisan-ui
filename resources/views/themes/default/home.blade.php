@extends(app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class)->resolve('layout'))

@php
    /** @var array<string, list<\Simtabi\Laranail\ArtisanUI\Core\Discovery\CommandDefinition>> $groups */
    /** @var list<\Simtabi\Laranail\ArtisanUI\Core\Presets\PresetGroup> $presets */
    /** @var list<array{label: string, value: string}>|null $environment */
    /** @var \Simtabi\Laranail\ArtisanUI\Core\Policy\RiskClassifier $risk */
    $theme = app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class);
@endphp

@section('content')
    <div class="grid gap-8 lg:grid-cols-[1fr_20rem]">
        <section aria-labelledby="lau-commands-heading" data-lau-command-list>
            <div class="mb-4 flex items-end justify-between gap-4">
                <h1 id="lau-commands-heading" class="text-2xl font-semibold">{{ __('laranail/artisan-ui::messages.commands') }}</h1>
            </div>

            <label for="lau-search" class="sr-only">{{ __('laranail/artisan-ui::messages.search') }}</label>
            <input
                id="lau-search"
                type="search"
                data-lau-search
                autocomplete="off"
                placeholder="{{ __('laranail/artisan-ui::messages.search_placeholder') }}"
                class="mb-4 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:ring-2 focus:ring-zinc-400 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900"
            >

            <p data-lau-no-matches hidden class="rounded border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:text-zinc-400 dark:border-zinc-700" role="status">
                {{ __('laranail/artisan-ui::messages.no_matches') }}
            </p>

            @forelse ($groups as $namespace => $commands)
                @php $groupId = $namespace === '' ? 'general' : $namespace; @endphp
                <details class="lau-group mb-2 rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" data-lau-group="{{ $groupId }}" id="group-{{ $groupId }}">
                    <summary class="flex cursor-pointer items-center justify-between px-4 py-2 font-mono text-sm font-medium">
                        <span>{{ $namespace === '' ? __('laranail/artisan-ui::messages.ungrouped') : $namespace }}</span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ count($commands) }}</span>
                    </summary>
                    <ul class="divide-y divide-zinc-100 border-t border-zinc-100 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($commands as $command)
                            <li data-lau-command data-name="{{ $command->name }}" data-description="{{ $command->description }}">
                                <a href="{{ route('laranail-artisan-ui.detail', $command->name) }}" class="flex items-start justify-between gap-4 px-4 py-2 hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:hover:bg-zinc-800 dark:focus:bg-zinc-800">
                                    <span class="min-w-0">
                                        <span class="block font-mono text-sm">{{ $command->name }}</span>
                                        <span class="block truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $command->description }}</span>
                                    </span>
                                    @include($theme->resolve('partials.risk-badge'), ['risk' => $risk->classify($command->name)])
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @empty
                <p class="rounded border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:text-zinc-400 dark:border-zinc-700">{{ __('laranail/artisan-ui::messages.no_commands') }}</p>
            @endforelse
        </section>

        <aside class="space-y-6">
            @if ($presets !== [])
                <section aria-labelledby="lau-presets-heading">
                    <h2 id="lau-presets-heading" class="mb-3 text-lg font-semibold">{{ __('laranail/artisan-ui::messages.quick_actions') }}</h2>
                    @foreach ($presets as $group)
                        <details class="mb-2 rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                            <summary class="cursor-pointer px-3 py-2 text-sm font-medium">{{ $group->label }}</summary>
                            @if ($group->description)
                                <p class="px-3 pb-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $group->description }}</p>
                            @endif
                            <ul class="border-t border-zinc-100 py-1 dark:border-zinc-800">
                                @foreach ($group->actions as $action)
                                    <li>
                                        @if ($action->isAvailable())
                                            <a href="{{ route('laranail-artisan-ui.detail', ['command' => $action->command, ...$action->prefill()]) }}" class="block px-3 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800" @if ($action->description) title="{{ $action->description }}" @endif>
                                                {{ $action->label }}
                                                <span class="block font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $action->command }}</span>
                                            </a>
                                        @else
                                            <span class="block cursor-not-allowed px-3 py-1.5 text-sm text-zinc-400" aria-disabled="true" title="{{ $action->unavailableReason }}">
                                                {{ $action->label }}
                                                <span class="block text-xs">{{ __('laranail/artisan-ui::messages.unavailable') }}: {{ $action->unavailableReason }}</span>
                                            </span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endforeach
                </section>
            @endif

            @if ($environment !== null)
                <section aria-labelledby="lau-env-heading" class="rounded-md border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 id="lau-env-heading" class="mb-2 text-lg font-semibold">{{ __('laranail/artisan-ui::messages.environment') }}</h2>
                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                        @foreach ($environment as $row)
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ $row['label'] }}</dt>
                            <dd class="font-mono">{{ $row['value'] }}</dd>
                        @endforeach
                    </dl>
                </section>
            @endif
        </aside>
    </div>
@endsection
