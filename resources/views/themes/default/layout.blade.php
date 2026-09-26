@php
    /** @var \Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets $assets */
    $assets = app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets::class);
    // Decided here rather than passed by each page, so every page shows the same navigation.
    $showHistoryLink = app(\Simtabi\Laranail\ArtisanUI\Core\Support\ArtisanUIConfig::class)->auditDriver() === \Simtabi\Laranail\ArtisanUI\Enums\AuditDriver::Database
        && \Illuminate\Support\Facades\Gate::allows(\Simtabi\Laranail\ArtisanUI\Enums\Ability::ViewHistory->value);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('laranail/artisan-ui::messages.title')) · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ $assets->url($assets::STYLE) }}">
    <script type="module" src="{{ $assets->url($assets::SCRIPT) }}"></script>
</head>
<body
    class="lau-body h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100"
    data-laranail-artisan-ui
    data-csrf-url="{{ route('laranail-artisan-ui.csrf-token') }}"
>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:shadow">{{ __('laranail/artisan-ui::messages.skip_to_content') }}</a>

    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
            <a href="{{ route('laranail-artisan-ui.home') }}" class="font-mono text-lg font-semibold tracking-tight">
                {{ __('laranail/artisan-ui::messages.title') }}
            </a>
            <nav class="flex gap-4 text-sm" aria-label="{{ __('laranail/artisan-ui::messages.title') }}">
                <a href="{{ route('laranail-artisan-ui.home') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">{{ __('laranail/artisan-ui::messages.commands') }}</a>
                @if ($showHistoryLink)
                    <a href="{{ route('laranail-artisan-ui.history') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">{{ __('laranail/artisan-ui::messages.history') }}</a>
                @endif
            </nav>
            <div class="ml-auto flex items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                <span class="hidden sm:inline">{{ app()->environment() }}</span>
                <button type="button" data-lau-theme-toggle class="rounded border border-zinc-300 px-2 py-1 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800" aria-pressed="false">
                    <span class="sr-only">{{ __('laranail/artisan-ui::messages.theme_toggle') }}</span>
                    <span aria-hidden="true">◐</span>
                </button>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-6xl px-4 py-8">
        @yield('content')
    </main>
</body>
</html>
