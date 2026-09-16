@extends('layouts.app')

@section('title', 'My Library — Academic Hub')

@section('content')
    {{-- Port of the Next.js library page: owned-paper cards with a book
         spine accent, plus saved free-material cards. --}}
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">My Library</h1>
            <p class="mt-0.5 text-sm text-neutral-500">Your purchased resources, forever yours</p>
        </div>
        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $entitlements->count() }} item{{ $entitlements->count() === 1 ? '' : 's' }}</span>
    </div>

    @if ($entitlements->isEmpty())
        <div class="mt-6 overflow-hidden rounded-xl border border-neutral-200 bg-white py-16 text-center shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50 ring-8 ring-brand-50/50 dark:bg-brand-950 dark:ring-brand-950/50">
                <svg class="h-8 w-8 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
            </span>
            <p class="mt-5 font-semibold text-neutral-900 dark:text-neutral-100">Your library is empty.</p>
            <p class="mx-auto mt-1 max-w-xs text-sm text-neutral-600 dark:text-neutral-400">Purchased past questions will appear here — buy your first one to get started.</p>
            <a href="{{ route('courses') }}" class="mt-6 inline-block rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">Browse Past Questions</a>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($entitlements as $entitlement)
                @php($resource = $entitlement->resource)
                @php($currentFile = $resource->files->firstWhere('is_current', true))
                <div class="group relative">
                    <div class="flex h-full flex-col gap-2.5 rounded-xl border border-neutral-200 bg-white p-5 shadow-soft transition-all group-hover:-translate-y-0.5 group-hover:shadow-lift dark:border-neutral-800 dark:bg-neutral-900">
                        {{-- Book spine accent --}}
                        <span class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-gradient-to-b from-brand-600 to-brand-800" aria-hidden="true"></span>
                        {{-- Laravel-extra: the favourite toggle (the original has
                             no favourites UI; keep the add-path reachable). --}}
                        <form method="POST" action="{{ route('favourites.toggle', $resource) }}" class="absolute right-3 top-3">
                            @csrf
                            <button class="text-lg leading-none {{ $savedIds->contains($resource->id) ? 'text-amber-500' : 'text-neutral-200 hover:text-amber-400 dark:text-neutral-700' }}"
                                    title="{{ $savedIds->contains($resource->id) ? 'Saved — click to remove' : 'Save for later' }}" aria-label="Toggle favourite">★</button>
                        </form>
                        <div class="pl-3">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $resource->course->code ?? '' }}</span>
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">Level {{ $resource->level }}</span>
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $resource->academic_year }}</span>
                            </div>
                            <h3 class="mt-2.5 font-semibold leading-snug text-neutral-900 dark:text-neutral-100">{{ $resource->title }}</h3>
                            <p class="mt-0.5 truncate text-sm text-neutral-600 dark:text-neutral-400">{{ $resource->programme->name ?? '' }} · {{ $resource->course->title ?? '' }}</p>
                            @if ($resource->bundle)
                                <a href="{{ route('bundles.show', $resource->bundle->slug) }}" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">
                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                    Part of {{ $resource->bundle->title }}
                                </a>
                            @endif
                            <p class="mt-2 text-xs text-neutral-400">Purchased {{ $entitlement->purchased_at?->format('d/m/Y') ?? '—' }} · Order {{ $entitlement->order->reference ?? '—' }}</p>
                        </div>
                        @if ($currentFile)
                            <a href="{{ route('viewer.show', $resource->slug) }}" class="mt-auto flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-soft transition hover:bg-brand-800">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                                Open
                            </a>
                        @else
                            <span class="mt-auto block w-full rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-2.5 text-center text-sm font-medium text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800">Coming soon</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Saved free materials — the Favourite bookmarks (never purchased). --}}
    @if ($savedMaterials->isNotEmpty())
        <h2 class="mt-10 text-sm font-bold uppercase tracking-wide text-neutral-400">Saved free materials</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($savedMaterials as $material)
                @php($file = $material->files->firstWhere('is_current', true))
                <div class="flex flex-col gap-2.5 rounded-xl border border-neutral-200 bg-white p-5 shadow-soft dark:border-neutral-800 dark:bg-neutral-900">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">{{ $material->course->code }}</span>
                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium capitalize text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ str_replace('_', ' ', strtolower($material->type)) }}</span>
                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Free</span>
                    </div>
                    <h3 class="font-semibold leading-snug text-neutral-900 dark:text-neutral-100">{{ $material->title }}</h3>
                    <p class="truncate text-sm text-neutral-600 dark:text-neutral-400">{{ $material->programme->name ?? '' }} · Level {{ $material->level }} · {{ $material->semester === 2 ? 'Second' : 'First' }} Semester</p>
                    <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                        <p class="text-xs text-neutral-500">{{ $file ? number_format($file->size_bytes / 1024) . ' KB' : '' }}{{ $material->page_count ? ' · '.$material->page_count.' pages' : '' }}</p>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('materials.download', $material->slug) }}" class="rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-800 shadow-soft transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-200" download>Download</a>
                            <form method="POST" action="{{ route('favourites.toggle', $material) }}">
                                @csrf
                                <button class="text-xs text-neutral-400 hover:text-red-600" title="Remove from saved">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
