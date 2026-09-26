@php
    /** @var \Simtabi\Laranail\ArtisanUI\Core\Discovery\InputField $field */
    /** @var array<string, string|list<string>> $prefill */
    $section = $field->kind() === 'argument' ? 'arguments' : 'options';
    $key = $section . '.' . $field->name;
    $value = $prefill[$key] ?? null;
    $name = $section . '[' . $field->name . ']';
    $describedBy = $field->domId() . '-help ' . $field->domId() . '-error';
@endphp
<div class="space-y-1">
    @if ($field instanceof \Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition && $field->isBoolean() && $field->negatable)
        {{-- Negatable flag: unset, --name, or --no-name. --}}
        <label for="{{ $field->domId() }}" class="block font-mono text-sm">--{{ $field->name }}</label>
        <select
            id="{{ $field->domId() }}"
            name="{{ $name }}"
            aria-describedby="{{ $describedBy }}"
            class="rounded-md border border-zinc-300 bg-white py-1.5 pr-9 pl-3 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-900"
        >
            <option value="">{{ __('laranail/artisan-ui::messages.not_set') }}</option>
            <option value="1" @selected($value === '1' || $value === 'true')>--{{ $field->name }}</option>
            <option value="no" @selected($value === 'no')>--no-{{ $field->name }}</option>
        </select>
    @elseif ($field->isBoolean())
        <div class="flex items-start gap-3">
            <input
                type="checkbox"
                id="{{ $field->domId() }}"
                name="{{ $name }}"
                value="1"
                @checked($value === '1' || $value === 'true')
                aria-describedby="{{ $describedBy }}"
                class="mt-1 size-4 rounded border-zinc-300 dark:border-zinc-600"
            >
            <label for="{{ $field->domId() }}" class="font-mono text-sm">--{{ $field->name }}</label>
        </div>
    @else
        <label for="{{ $field->domId() }}" id="{{ $field->domId() }}-label" class="flex gap-2 text-sm">
            <span class="font-mono font-medium">{{ $field->kind() === 'option' ? '--' . $field->name : $field->name }}</span>
            @if ($field->required)
                <span class="text-zinc-500 dark:text-zinc-400">· {{ __('laranail/artisan-ui::messages.required') }}</span>
            @endif
            @if ($field->array)
                <span class="text-zinc-500 dark:text-zinc-400">· {{ __('laranail/artisan-ui::messages.array') }}</span>
            @endif
        </label>

        @if ($field->array)
            @php $values = is_array($value) ? $value : []; @endphp
            <div data-lau-array data-name="{{ $name }}[]" class="space-y-1">
                <div data-lau-array-rows class="space-y-1">
                    @foreach ([...$values, ''] as $index => $item)
                        <div data-lau-array-row class="flex gap-2">
                            <input
                                type="text"
                                name="{{ $name }}[]"
                                value="{{ $item }}"
                                @if ($index === 0) id="{{ $field->domId() }}" @endif
                                {{-- Every row, not only the first, needs a name for assistive tech. --}}
                                aria-labelledby="{{ $field->domId() }}-label"
                                placeholder="{{ $field->defaultForDisplay() }}"
                                aria-describedby="{{ $describedBy }}"
                                class="w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-900"
                            >
                            <button type="button" data-lau-array-remove class="rounded border border-zinc-300 px-2 text-sm dark:border-zinc-700" aria-label="{{ __('laranail/artisan-ui::messages.remove_value') }}">×</button>
                        </div>
                    @endforeach
                </div>
                <button type="button" data-lau-array-add class="text-xs text-zinc-600 underline hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                    {{ __('laranail/artisan-ui::messages.add_value') }}
                </button>
            </div>
        @else
            <input
                type="text"
                id="{{ $field->domId() }}"
                name="{{ $name }}"
                value="{{ is_string($value) ? $value : '' }}"
                placeholder="{{ $field->defaultForDisplay() }}"
                @if ($field->kind() === 'argument' && $field->required) required @endif
                aria-describedby="{{ $describedBy }}"
                class="w-full rounded-md border border-zinc-300 bg-white px-3 py-1.5 font-mono text-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
            @if ($field instanceof \Simtabi\Laranail\ArtisanUI\Core\Discovery\OptionDefinition && ! $field->required)
                {{-- A value-optional option can be given with no value at all (`--name`). --}}
                <label class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                    <input type="checkbox" name="options-present[{{ $field->name }}]" value="1" @checked(($prefill['options-present.' . $field->name] ?? null) === '1') class="size-4 rounded border-zinc-300 dark:border-zinc-600">
                    {{ __('laranail/artisan-ui::messages.send_without_value', ['option' => '--' . $field->name]) }}
                </label>
            @endif
        @endif
    @endif

    <p id="{{ $field->domId() }}-help" class="text-xs text-zinc-500 dark:text-zinc-400">{{ $field->description }}</p>
    <p id="{{ $field->domId() }}-error" data-lau-error-for="{{ $key }}" class="text-xs text-red-600 dark:text-red-400" hidden></p>
</div>
