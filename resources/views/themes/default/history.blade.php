@extends(app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\ThemeView::class)->resolve('layout'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \Simtabi\Laranail\ArtisanUI\Core\Audit\CommandRun> $runs */
    /** @var \Simtabi\Laranail\ArtisanUI\Core\Output\AnsiFormatter $ansi */
    /** @var list<\Simtabi\Laranail\ArtisanUI\Enums\RunStatus> $statuses */
@endphp

@section('title', __('laranail/artisan-ui::messages.history'))

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ __('laranail/artisan-ui::messages.history') }}</h1>

    <form method="get" action="{{ route('laranail-artisan-ui.history') }}" class="mb-6 flex flex-wrap items-end gap-3">
        <div>
            <label for="lau-history-command" class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.history_command') }}</label>
            <input id="lau-history-command" type="text" name="command" value="{{ $command }}" class="rounded-md border border-zinc-300 bg-white px-3 py-1.5 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-900">
        </div>
        <div>
            <label for="lau-history-status" class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.history_status') }}</label>
            <select id="lau-history-status" name="status" class="rounded-md border border-zinc-300 bg-white py-1.5 pr-9 pl-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="">{{ __('laranail/artisan-ui::messages.history_all') }}</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md border border-zinc-300 px-3 py-1.5 text-sm dark:border-zinc-700">{{ __('laranail/artisan-ui::messages.history_filter') }}</button>
    </form>

    @if ($runs->isEmpty())
        <p class="rounded border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:text-zinc-400 dark:border-zinc-700">{{ __('laranail/artisan-ui::messages.history_empty') }}</p>
    @else
        <ol class="space-y-2">
            @foreach ($runs as $run)
                <li class="rounded-md border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <details>
                        <summary class="flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm">
                            <span class="font-mono font-medium">{{ $run->command }}</span>
                            <span @class([
                                'rounded px-1.5 py-0.5 text-xs',
                                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => $run->status === \Simtabi\Laranail\ArtisanUI\Enums\RunStatus::Succeeded,
                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $run->status === \Simtabi\Laranail\ArtisanUI\Enums\RunStatus::Failed,
                                'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => $run->status === \Simtabi\Laranail\ArtisanUI\Enums\RunStatus::Errored,
                                'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' => $run->status === \Simtabi\Laranail\ArtisanUI\Enums\RunStatus::Running,
                            ])>{{ $run->status->label() }}</span>
                            <span class="text-zinc-500 dark:text-zinc-400"><span class="sr-only">{{ __('laranail/artisan-ui::messages.history_user') }}: </span>{{ $run->user_id ?? __('laranail/artisan-ui::messages.history_nobody') }}</span>
                            <time class="text-zinc-500 dark:text-zinc-400" datetime="{{ $run->created_at?->toIso8601String() }}">{{ $run->created_at?->diffForHumans() }}</time>
                            @if ($run->duration_ms !== null)
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.duration', ['ms' => $run->duration_ms]) }}</span>
                            @endif
                        </summary>
                        <div class="space-y-2 border-t border-zinc-100 px-4 py-3 text-xs dark:border-zinc-800">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-mono text-zinc-500 dark:text-zinc-400">{{ __('laranail/artisan-ui::messages.run_id', ['run' => $run->id]) }}</p>
                                {{-- Outside <summary>: a link nested in a disclosure button is unreachable for many assistive tools. --}}
                                <a href="{{ route('laranail-artisan-ui.detail', ['command' => $run->command, 'arguments' => $run->arguments ?? [], 'options' => array_map(static fn ($v) => $v ?? '', $run->options ?? [])]) }}" class="text-xs underline">{{ __('laranail/artisan-ui::messages.history_rerun') }}: {{ $run->command }}</a>
                            </div>
                            @if ($run->error)
                                <p class="text-red-700 dark:text-red-400">{{ $run->error }}</p>
                            @endif
                            @if ($run->output !== null && $run->output !== '')
                                <pre class="lau-output max-h-96 overflow-auto rounded bg-zinc-900 p-3 font-mono leading-relaxed text-zinc-100">@foreach ($ansi->segments($run->output) as $segment)<span class="{{ $segment['classes'] }}">{{ $segment['text'] }}</span>@endforeach</pre>
                            @endif
                        </div>
                    </details>
                </li>
            @endforeach
        </ol>

        <div class="mt-6">{{ $runs->links() }}</div>
    @endif
@endsection
