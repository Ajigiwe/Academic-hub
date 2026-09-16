{{--
    A password input with a show/hide eye.

    Expects: $name, $label. Optional: $id, $autocomplete, $hint,
    $autofocus.

    The toggle is progressive enhancement — without JS the field stays
    masked and nothing is lost. The script lives in the layout so every
    password field on the site behaves the same way.
--}}
@php
    $id = $id ?? $name;
    $hint = $hint ?? null;
    $autocomplete = $autocomplete ?? null;
    $autofocus = $autofocus ?? false;
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">{{ $label }}</label>

    <div class="relative">
        <input id="{{ $id }}" type="password" name="{{ $name }}" required
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @if ($autofocus) autofocus @endif
               class="w-full rounded-lg border border-neutral-300 bg-white py-2.5 pl-3.5 pr-11 text-base text-neutral-900 shadow-sm outline-none transition focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100">

        <button type="button" data-password-toggle="{{ $id }}"
                aria-label="Show password" aria-pressed="false" title="Show password"
                class="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-lg text-neutral-400 transition hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-600/40 dark:hover:text-neutral-200">
            <svg class="pw-icon-show h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="pw-icon-hide hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9.9 4.24A9.9 9.9 0 0 1 12 4c6.4 0 10 7 10 7a18 18 0 0 1-2.16 3.19"/>
                <path d="M6.61 6.61A18 18 0 0 0 2 11s3.6 7 10 7a9.9 9.9 0 0 0 5.39-1.61"/>
                <path d="m2 2 20 20"/>
                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
            </svg>
        </button>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $hint }}</p>
    @endif
    @error($name) <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
</div>
