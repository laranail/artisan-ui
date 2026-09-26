@php
    /** @var int $status */
    /** @var string $message */
    $assets = app(\Simtabi\Laranail\ArtisanUI\Modules\WebUI\Support\Assets::class);

    // The asset route is absent while the panel is disabled; the page must still render.
    try {
        $stylesheet = $assets->url($assets::STYLE);
    } catch (\Throwable) {
        $stylesheet = null;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · {{ config('app.name') }}</title>
    @if ($stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endif
</head>
<body class="flex h-full items-center justify-center bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100" data-lau-denied>
    <main class="max-w-md px-6 text-center">
        <p class="font-mono text-5xl font-semibold text-zinc-400">{{ $status }}</p>
        <h1 class="mt-4 text-xl font-semibold">{{ $message }}</h1>
    </main>
</body>
</html>
