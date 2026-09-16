@extends('layouts.app')

@section('title', $state['title'])

{{--
    One page, five states — success, pending, refunded, cancelled, failed.

    They share a single shell (icon, headline, lead, receipt, actions,
    support line) so the family looks deliberate rather than ad hoc, and
    the receipt comes from the controller (our own order records), never
    from the query string. No receipt → the reference pill is all we show,
    which is also what a guest or a wrong-account visitor gets.
--}}
@php
    $icons = [
        'success' => ['path' => 'M20 6 9 17l-5-5', 'ring' => 'bg-emerald-50 ring-emerald-50/60 dark:bg-emerald-950 dark:ring-emerald-950/60', 'ink' => 'text-emerald-600 dark:text-emerald-400'],
        'pending' => ['path' => 'M12 7v5l3 2', 'circle' => true, 'ring' => 'bg-brand-50 ring-brand-50/60 dark:bg-neutral-800 dark:ring-neutral-800/60', 'ink' => 'text-brand-700 dark:text-brand-300'],
        'refunded' => ['path' => 'M3 12a9 9 0 1 0 3-6.7M3 4v5h5', 'ring' => 'bg-neutral-100 ring-neutral-100/60 dark:bg-neutral-800 dark:ring-neutral-800/60', 'ink' => 'text-neutral-500 dark:text-neutral-300'],
        'cancelled' => ['path' => 'M9 14 4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11', 'ring' => 'bg-neutral-100 ring-neutral-100/60 dark:bg-neutral-800 dark:ring-neutral-800/60', 'ink' => 'text-neutral-500 dark:text-neutral-300'],
        'failed' => ['path' => 'm21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3M12 9v4M12 17h.01', 'ring' => 'bg-amber-50 ring-amber-50/60 dark:bg-amber-950 dark:ring-amber-950/60', 'ink' => 'text-amber-600 dark:text-amber-400'],
    ];
    $icon = $icons[$status];

    // Primary action per state: keep the student moving forward, not back.
    [$primaryHref, $primaryLabel] = match ($status) {
        'success' => [$bundleHref ?? route('library'), $bundleHref ? 'Start reading' : 'Open My Library'],
        'pending' => [route('library'), 'Go to My Library'],
        'refunded' => [route('courses'), 'Browse resources'],
        'cancelled', 'failed' => [$bundleHref ?? route('courses'), $bundleHref ? 'Try the payment again' : 'Browse resources'],
    };

    $secondary = match ($status) {
        'success' => $bundleHref ? [route('library'), 'My Library'] : [route('courses'), 'Keep browsing'],
        'pending' => [route('courses'), 'Keep browsing'],
        'refunded' => [route('library'), 'My Library'],
        'cancelled', 'failed' => [route('library'), 'Check My Library'],
    };
@endphp

@section('content')
    <div class="container-page flex justify-center py-12 sm:py-16">
        <div class="w-full max-w-xl">
            <div class="text-center">
                <div class="mx-auto grid h-16 w-16 place-items-center rounded-full ring-8 {{ $icon['ring'] }}">
                    <svg class="h-8 w-8 {{ $icon['ink'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if ($icon['circle'] ?? false)
                            <circle cx="12" cy="12" r="9"/>
                        @endif
                        <path d="{{ $icon['path'] }}"/>
                    </svg>
                </div>

                <h1 class="mt-5 font-display text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-white">
                    {{ $state['title'] }}
                </h1>

                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                    {{ $reason ?: $state['lead'] }}
                </p>

                @unless ($receipt)
                    @if ($ref)
                        <p class="mt-4 inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1 font-mono text-xs text-neutral-600 shadow-sm dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300">
                            {{ $ref }}
                        </p>
                    @endif

                    {{-- No receipt to show: say why, and offer the one action
                         that fixes the usual cause (a missing session). --}}
                    @if ($receiptHint)
                        <p class="mx-auto mt-4 max-w-sm text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                            {{ $receiptHint['text'] }}
                            @if ($receiptHint['href'])
                                <a href="{{ $receiptHint['href'] }}" class="font-semibold text-brand-700 underline underline-offset-2 hover:no-underline dark:text-brand-300">{{ $receiptHint['label'] }}</a>
                            @endif
                        </p>
                    @endif
                @endunless
            </div>

            @if ($receipt)
                <section class="card mt-7 overflow-hidden text-left" aria-labelledby="order-summary">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 px-5 py-3 dark:border-neutral-800">
                        <h2 id="order-summary" class="text-[11px] font-semibold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">
                            Order summary
                        </h2>
                        <span class="{{ $state['badge'] }} font-semibold">{{ $state['label'] }}</span>
                    </div>

                    <dl class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Order</dt>
                            <dd class="font-mono text-sm text-neutral-900 dark:text-neutral-100">{{ $receipt['reference'] }}</dd>
                        </div>

                        <div class="px-5 py-3">
                            <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                                {{ count($receipt['items']) > 1 ? 'Items' : 'Item' }}
                            </dt>
                            <dd class="mt-1.5 space-y-2.5">
                                @foreach ($receipt['items'] as $item)
                                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span class="badge-brand shrink-0">{{ $item['code'] }}</span>
                                        <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $item['title'] }}</span>
                                        @if ($item['meta'])
                                            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $item['meta'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </dd>
                        </div>

                        @foreach ($receipt['rows'] as $row)
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3">
                                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $row['label'] }}</dt>
                                <dd class="text-sm font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">
                                    {{ $row['value'] }}
                                    @if ($row['note'])
                                        <span class="ml-1 text-xs font-normal text-neutral-500 dark:text-neutral-400">{{ $row['note'] }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach

                        @if ($receipt['access'])
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3">
                                <dt class="text-xs font-medium uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Access</dt>
                                <dd class="text-sm text-neutral-700 dark:text-neutral-200">{{ $receipt['access'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif

            <div class="mt-7 flex flex-wrap justify-center gap-3">
                <a href="{{ $primaryHref }}" class="btn-primary">{{ $primaryLabel }}</a>
                <a href="{{ $secondary[0] }}" class="btn-secondary">{{ $secondary[1] }}</a>
            </div>

            <p class="mx-auto mt-6 max-w-md text-center text-xs text-neutral-500 dark:text-neutral-400">
                @if ($ref)
                    Keep order reference <span class="font-mono">{{ $ref }}</span> for support —
                    if you were debited without access, we reconcile it with the gateway and put it right.
                @else
                    If you were debited without access, contact support with your order reference and we will put it right.
                @endif
            </p>
        </div>
    </div>
@endsection
