@php /** @var \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk $risk */ @endphp
@if ($risk !== \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk::Safe)
    <span @class([
        'inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium',
        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $risk === \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk::WritesFiles,
        'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => $risk === \Simtabi\Laranail\ArtisanUI\Enums\CommandRisk::Destructive,
    ]) title="{{ $risk->help() }}">{{ $risk->label() }}</span>
@endif
