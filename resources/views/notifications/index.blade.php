@extends('layouts.app')

@section('title', 'Notifications — Academic Hub')

{{--
    The student's bell.

    Each row is a way in, not a note: it names what happened, when, and
    links to the page that shows the detail (a purchase's receipt, a
    refund's outcome) — opening it clears its unread mark. Rows are
    grouped by day so a long history stays scannable, and the whole list
    is scoped to the signed-in student by the controller.
--}}
@php
    // Icon + tint per notification type. An unknown type still renders —
    // with a neutral bell — so a future notice never breaks the page.
    $styles = [
        'PURCHASE' => [
            'icon' => 'bag',
            'tint' => 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-emerald-900',
            'cta' => 'View receipt',
        ],
        'PAYMENT' => [
            'icon' => 'refund',
            'tint' => 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-900',
            'cta' => 'See what happened',
        ],
        'PROMO' => [
            'icon' => 'bell',
            'tint' => 'bg-brand-50 text-brand-700 ring-brand-100 dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900',
            'cta' => 'Open',
        ],
    ];
    $fallback = [
        'icon' => 'bell',
        'tint' => 'bg-neutral-100 text-neutral-600 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
        'cta' => 'Open',
    ];
    $paths = [
        'bag' => ['M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z', 'M3 6h18', 'M16 10a4 4 0 0 1-8 0'],
        'refund' => ['M3 12a9 9 0 1 0 3-6.7', 'M3 4v5h5'],
        'bell' => ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
    ];
@endphp

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold tracking-tight text-neutral-900 dark:text-white">Notifications</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                @if ($unread > 0)
                    {{ $unread }} unread — opening one marks it read.
                @else
                    You're all caught up.
                @endif
            </p>
        </div>

        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn-secondary btn-sm">Mark all as read</button>
            </form>
        @endif
    </div>

    @forelse ($groups as $day => $rows)
        <h2 class="mb-2 mt-6 px-1 text-[11px] font-bold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">
            {{ $day }}
        </h2>

        <ul class="divide-y divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900">
            @foreach ($rows as $notification)
                @php
                    $style = $styles[$notification->type] ?? $fallback;
                    $href = $notification->destination();
                    $href = $href ? route('notifications.open', $notification) : null;
                    $isLink = $href !== null;
                    $tag = $isLink ? 'a' : 'div';
                @endphp

                <li class="{{ $notification->isUnread() ? 'bg-brand-50/40 dark:bg-brand-950/25' : '' }}">
                    <{{ $tag }}
                        @if ($isLink) href="{{ $href }}" @endif
                        class="flex items-start gap-3.5 px-4 py-4 sm:px-5 {{ $isLink ? 'group transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500/40 dark:hover:bg-neutral-800/60' : '' }}">

                        <span class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-full ring-1 {{ $style['tint'] }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                @foreach ($paths[$style['icon']] as $path)
                                    <path d="{{ $path }}"/>
                                @endforeach
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <span class="text-sm font-semibold {{ $notification->isUnread() ? 'text-neutral-900 dark:text-white' : 'text-neutral-700 dark:text-neutral-200' }}">
                                    {{ $notification->title }}
                                </span>
                                <time class="shrink-0 text-xs text-neutral-400 dark:text-neutral-500"
                                      datetime="{{ $notification->created_at?->toIso8601String() }}"
                                      title="{{ $notification->created_at?->format('j M Y, H:i') }}">
                                    {{ $notification->created_at?->diffForHumans() }}
                                </time>
                            </span>

                            <span class="mt-0.5 block text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                                {{ $notification->body }}
                            </span>

                            <span class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
                                    {{ $notification->typeLabel() }}
                                </span>
                                @if ($isLink)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-brand-700 dark:text-brand-300">
                                        {{ $style['cta'] }}
                                        <svg class="h-3 w-3 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m9 18 6-6-6-6"/>
                                        </svg>
                                    </span>
                                @endif
                            </span>
                        </span>

                        @if ($notification->isUnread())
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-brand-600 dark:bg-brand-400" aria-hidden="true"></span>
                            <span class="sr-only">Unread</span>
                        @endif
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>
    @empty
        <div class="card-padded mt-6 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-neutral-100 text-neutral-400 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-500 dark:ring-neutral-700">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
            </span>
            <p class="mt-4 text-sm font-semibold text-neutral-900 dark:text-white">Nothing here yet</p>
            <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                Purchase confirmations, refund outcomes and account notices land here — nothing to clear yet.
            </p>
            <a href="{{ route('courses') }}" class="btn-primary btn-sm mt-5">Browse past questions</a>
        </div>
    @endforelse

    <div class="mt-6">{{ $notifications->links() }}</div>
@endsection
