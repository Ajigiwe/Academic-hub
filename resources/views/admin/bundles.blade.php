@extends('admin.layout')

@section('title', 'Admin — Bundles')

@section('admin-content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Bundles</h1>
            <p class="mt-1 max-w-3xl text-sm text-neutral-600 dark:text-neutral-400">
                The unit of sale — students pay once for a bundle (course + academic year) and unlock every paper
                inside it. Upload papers in bulk from the
                <a href="{{ route('admin.papers') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Papers</a>
                page, then publish the bundle here to put everything on sale.
            </p>
        </div>
        <a href="{{ route('admin.papers') }}" class="btn-primary btn-sm">Upload papers</a>
    </div>

    <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-base font-semibold text-neutral-900 dark:text-white">
            {{ number_format($bundles->total()) }} {{ Str::plural('bundle', $bundles->total()) }}
        </h2>
        <p class="text-xs text-neutral-500 dark:text-neutral-400">
            Every bundle sells for <span class="font-semibold text-neutral-700 dark:text-neutral-300">GHS {{ \App\Services\BundlePricing::formatted() }}</span> —
            set the price in
            <a href="{{ route('admin.settings') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Settings</a>
        </p>
    </div>

    <div class="mt-3 grid grid-cols-1 gap-4 xl:grid-cols-2">
        @forelse ($bundles as $bundle)
            <article class="card flex flex-col overflow-hidden">
                {{-- ── Identity ─────────────────────────────────────────── --}}
                <div class="flex items-start justify-between gap-3 border-b border-neutral-100 px-4 py-3.5 sm:px-5 dark:border-neutral-800">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="badge-brand font-semibold">{{ $bundle->course->code }}</span>
                            <span class="badge-neutral">Level {{ $bundle->level }}</span>
                            <span class="badge-neutral">{{ $bundle->academic_year }}</span>
                            @if ($bundle->solved)
                                <span class="badge-success font-semibold">Solved</span>
                            @endif
                        </div>
                        <h3 class="mt-2 text-[15px] font-bold leading-snug text-neutral-900 dark:text-white">
                            <a href="{{ route('bundles.show', $bundle->slug) }}"
                               class="transition-colors hover:text-brand-800 hover:underline dark:hover:text-brand-300">{{ $bundle->title }}</a>
                        </h3>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $bundle->programme->name }} · {{ $bundle->course->title }}
                        </p>
                    </div>
                    <span class="shrink-0 {{ match ($bundle->status) {
                        'PUBLISHED' => 'badge-success',
                        'UNPUBLISHED' => 'badge-gold',
                        'ARCHIVED' => 'badge-danger',
                        default => 'badge-neutral',
                    } }}">{{ $bundle->status }}</span>
                </div>

                {{-- ── Numbers ──────────────────────────────────────────── --}}
                <dl class="grid grid-cols-3 divide-x divide-neutral-100 border-b border-neutral-100 text-center dark:divide-neutral-800 dark:border-neutral-800">
                    <div class="px-2 py-2.5">
                        <dd class="text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{{ $bundle->resources_count }}</dd>
                        <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ Str::plural('paper', $bundle->resources_count) }}</dt>
                    </div>
                    <div class="px-2 py-2.5">
                        <dd class="text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{{ $bundle->order_items_count }}</dd>
                        <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ Str::plural('sale', $bundle->order_items_count) }}</dt>
                    </div>
                    <div class="px-2 py-2.5">
                        <dd class="text-sm font-bold tabular-nums text-brand-800 dark:text-brand-300">GHS {{ $bundle->priceCedis() }}</dd>
                        <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Platform price</dt>
                    </div>
                </dl>

                {{-- ── Actions ──────────────────────────────────────────── --}}
                <div class="mt-auto flex flex-wrap items-center gap-2 px-4 pb-4 pt-3.5 sm:px-5">
                    <a href="{{ route('admin.papers') }}" class="btn-secondary btn-sm">Add papers</a>
                    @if ($bundle->status !== 'PUBLISHED')
                        <form method="POST" action="{{ route('admin.bundles.status', $bundle) }}">
                            @csrf
                            <input type="hidden" name="next" value="PUBLISHED">
                            <button class="btn-primary btn-sm" @disabled($bundle->resources_count === 0)
                                    title="{{ $bundle->resources_count === 0 ? 'Add at least one paper before publishing' : 'Publish bundle and all its papers' }}">
                                Publish
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.bundles.status', $bundle) }}">
                            @csrf
                            <input type="hidden" name="next" value="UNPUBLISHED">
                            <button class="btn-secondary btn-sm">Unpublish</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.bundles.destroy', $bundle) }}" class="sm:ml-auto"
                          onsubmit="return confirm('Delete “{{ $bundle->title }}” and its {{ $bundle->resources_count }} paper(s)? This cannot be undone.')">
                        @csrf @method('DELETE')
                        <button class="btn-danger btn-sm" @disabled($bundle->order_items_count > 0)
                                title="{{ $bundle->order_items_count > 0 ? 'Has '.$bundle->order_items_count.' order(s) — cannot be deleted' : 'Permanently delete “'.$bundle->title.'”' }}">
                            Delete
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="card px-6 py-14 text-center xl:col-span-2">
                <p class="font-semibold text-neutral-800 dark:text-neutral-100">No bundles yet</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-400">
                    Upload your first batch of papers from the Papers page — a bundle is created per course and
                    academic year, then published from here.
                </p>
                <a href="{{ route('admin.papers') }}" class="btn-primary mt-4">Upload papers</a>
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $bundles->links() }}</div>

    <p class="mt-4 text-xs text-neutral-500 dark:text-neutral-400">
        Publishing a bundle also publishes every paper inside it; unpublishing hides the bundle from the
        catalog while keeping existing owners' access. Papers published later are granted automatically to
        everyone who already bought the bundle — late uploads reach past buyers with no extra steps. Deleting
        a bundle also deletes its papers, and is only possible while nothing has been sold — purchase records
        are permanent.
    </p>
@endsection
