@extends('layouts.app')

@section('title', $bundle->title)

@section('content')
    {{-- Port of the Next.js bundle detail page: badges, breadcrumb,
         papers list, and the sticky buy box (mobile buy bar below). --}}
    <div class="relative -mx-4 border-b border-neutral-200 bg-gradient-to-b from-brand-50/60 to-neutral-50 sm:-mx-6 dark:border-neutral-800">
        @if (! $owned && $papers->isNotEmpty())
            {{-- Mobile buy bar — pinned above the bottom nav (z-30 < z-40). --}}
            <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 border-t border-neutral-200 bg-white/95 px-4 py-2.5 shadow-[0_-2px_10px_rgb(16_24_40/0.06)] backdrop-blur-md md:hidden dark:border-neutral-800 dark:bg-neutral-950/95">
                <div class="mx-auto flex max-w-lg items-center gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-base font-extrabold tracking-tight text-neutral-900 dark:text-neutral-100">GHS {{ $bundle->priceCedis() }}</p>
                        <p class="truncate text-[11px] text-neutral-500 dark:text-neutral-400">{{ $papers->count() }} paper{{ $papers->count() === 1 ? '' : 's' }} · one price</p>
                    </div>
                    <form method="POST" action="{{ route('checkout.store') }}" class="ml-auto shrink-0">
                        @csrf
                        <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                        <button class="rounded-lg bg-gold-500 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-gold-600">Buy Bundle</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
            <nav class="text-sm text-neutral-500 dark:text-neutral-400" aria-label="Breadcrumb">
                <a href="{{ route('courses') }}" class="transition-colors hover:text-brand-700 dark:hover:text-brand-400">Browse</a>
                <span class="mx-1.5 text-neutral-300 dark:text-neutral-600">/</span>
                <span class="text-neutral-700 dark:text-neutral-300">{{ $bundle->course->code }}</span>
            </nav>

            <div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                {{-- Main column --}}
                <div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $bundle->course->code }}</span>
                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">Level {{ $bundle->level }}</span>
                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $bundle->academic_year }}</span>
                        @if ($bundle->solved)
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Solved</span>
                        @endif
                        @if ($papers->count() > 0)
                            <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $papers->pluck('semester')->unique()->sort()->map(fn ($s) => ($s === 2 ? 'Second' : 'First').' Semester')->implode(' & ') }}</span>
                        @endif
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ $papers->count() }} paper{{ $papers->count() === 1 ? '' : 's' }} · one price</span>
                    </div>

                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-neutral-100">{{ $bundle->course->title }}</h1>

                    <p class="mt-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">{{ $bundle->programme->name }} · {{ $bundle->academic_year }}</p>

                    @if ($bundle->description)
                        <p class="mt-4 whitespace-pre-line leading-relaxed text-neutral-700 dark:text-neutral-300">{{ $bundle->description }}</p>
                    @endif

                    {{-- Papers inside the bundle --}}
                    <h2 class="mt-8 flex items-center gap-2 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        What's inside
                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $papers->count() }}</span>
                    </h2>

                    @if ($papers->isEmpty())
                        <p class="mt-3 rounded-xl border border-dashed border-neutral-300 bg-white p-6 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                            Papers for this bundle are being prepared — check back soon.
                        </p>
                    @else
                        <ul class="mt-3 divide-y divide-neutral-100 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-soft dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900">
                            @foreach ($papers as $paper)
                                <li>
                                    @auth
                                        {{-- Owned papers deep-link into the secure viewer —
                                             including a partial owner's papers. --}}
                                        <a href="{{ $ownedResourceIds->contains($paper->id) ? route('viewer.show', $paper->slug) : '#' }}"
                                           class="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    @else
                                        <a href="{{ route('login') }}" class="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800">
                                    @endauth
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/></svg>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $paper->title }}</p>
                                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $paper->semester === 2 ? 'Second' : 'First' }} Semester{{ $paper->page_count ? ' · '.$paper->page_count.' pages' : '' }}</p>
                                            </div>
                                            <svg class="h-4 w-4 shrink-0 text-neutral-300 dark:text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                        </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Buy box --}}
                <aside class="pb-16 lg:sticky lg:top-24 lg:self-start md:pb-0">
                    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="h-1 w-full bg-gradient-to-r from-brand-600 to-gold-400"></div>
                        <div class="p-5">
                            <p class="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-100">GHS {{ $bundle->priceCedis() }}</p>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">One payment · every paper inside · lifetime access</p>

                            @auth
                                @if ($owned)
                                    <a href="{{ route('library') }}" class="mt-5 block w-full rounded-lg bg-brand-700 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">Open in Library</a>
                                @elseif ($papers->isNotEmpty())
                                    <form method="POST" action="{{ route('checkout.store') }}">
                                        @csrf
                                        <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                                        <button class="mt-5 w-full rounded-lg bg-gold-500 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-gold-600">Buy Bundle</button>
                                    </form>
                                    <p class="mt-3 text-center text-[11px] text-neutral-500 dark:text-neutral-400">{{ $papers->count() }} paper{{ $papers->count() === 1 ? '' : 's' }} · Mobile Money &amp; cards accepted</p>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="mt-5 block w-full rounded-lg bg-gold-500 px-4 py-2.5 text-center text-sm font-semibold text-white shadow-soft transition hover:bg-gold-600">Log in to buy</a>
                                <p class="mt-3 text-center text-[11px] text-neutral-500 dark:text-neutral-400">{{ $papers->count() }} paper{{ $papers->count() === 1 ? '' : 's' }} · Mobile Money &amp; cards accepted</p>
                            @endif

                            @error('bundle')
                                <p class="mt-3 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror

                            <ul class="mt-5 space-y-2.5 border-t border-neutral-100 pt-4 text-xs text-neutral-600 dark:border-neutral-800 dark:text-neutral-400">
                                <li class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    All papers unlock instantly after payment
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    New papers added to this bundle stay free for you
                                </li>
                                <li class="flex items-start gap-2">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    Read on any device, forever
                                </li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
@endsection
