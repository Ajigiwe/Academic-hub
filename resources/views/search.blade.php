@extends('layouts.app')

@section('title', $q ? "Search “{$q}” — Academic Hub" : 'Browse all bundles — Academic Hub')

@section('content')
<?php use App\Support\Format; ?>
<main class="flex-1">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <h1 class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-white">
      @if($q !== '')
        Search results for &ldquo;{{ $q }}&rdquo;
      @else
        Browse all bundles
      @endif
    </h1>
    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
      @if($bundles->total() > 0)
        {{ $bundles->total() }} {{ Str::plural('bundle', $bundles->total()) }} found — one price unlocks every paper inside
      @endif
    </p>

    <div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
      {{-- ── Filters (collapsible; collapsed by default on phones) ── --}}
      <form method="GET" action="{{ route('search') }}" class="card h-fit self-start overflow-hidden">
        <style>
          .filters-body { display: block; }
          .filters-body.filters-closed { display: none; }
          @media (min-width: 1024px) {
            .filters-body.filters-closed { display: block; }
            .filters-chevron.filters-closed { transform: none !important; }
          }
        </style>
        <div>
          <button type="button" id="filters-toggle" aria-expanded="{{ $activeFilterCount > 0 ? 'true' : 'false' }}"
                  class="flex w-full items-center justify-between gap-2 px-5 py-4 text-left">
            <span class="flex items-center gap-2 text-sm font-semibold text-neutral-900 dark:text-white">
              <svg class="h-4 w-4 text-brand-700 dark:text-brand-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>
              </svg>
              Filters
              @if($activeFilterCount > 0)
                <span class="badge-brand grid h-5 min-w-5 place-items-center rounded-full px-1.5 text-[11px] font-bold">{{ $activeFilterCount }}</span>
              @endif
            </span>
            <span class="flex items-center gap-2">
              @if($activeFilterCount > 0)
                <a href="{{ route('search') }}" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-300">Clear</a>
              @endif
              <svg id="filters-chevron" class="h-4 w-4 text-neutral-400 transition-transform {{ $activeFilterCount > 0 ? '' : 'filters-closed rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m6 9 6 6 6-6"/>
              </svg>
            </span>
          </button>
          <div id="filters-body" class="filters-body {{ $activeFilterCount > 0 ? '' : 'filters-closed' }}">
            <div class="space-y-4 border-t border-neutral-100 px-5 pb-5 pt-4 dark:border-neutral-800">
              <input type="hidden" name="sort" value="{{ $sort }}">
              <div>
                <label class="label" for="q">Search</label>
                <input id="q" name="q" value="{{ $q }}" class="input" placeholder="Course code, title…">
              </div>
              <div>
                <label class="label" for="level">Level</label>
                <select id="level" name="level" class="input">
                  <option value="">Any</option>
                  @foreach(\App\Models\Programme::LEVELS as $l)
                    <option value="{{ $l }}" @selected($level === $l)>Level {{ $l }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="label" for="semester">Semester</label>
                <select id="semester" name="semester" class="input">
                  <option value="">Any</option>
                  <option value="1" @selected($semester === 1)>First</option>
                  <option value="2" @selected($semester === 2)>Second</option>
                </select>
              </div>
              <div>
                <label class="label" for="year">Academic year</label>
                <select id="year" name="year" class="input">
                  <option value="">Any</option>
                  @foreach($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                  @endforeach
                </select>
              </div>
              <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1">Apply</button>
                <a href="{{ route('search') }}" class="btn-secondary">Clear</a>
              </div>
            </div>
          </div>
        </div>
      </form>

      {{-- ── Results ───────────────────────────────────────────────── --}}
      <div>
        @if($bundles->total() === 0)
          @if($freeMaterials->isNotEmpty())
            <div class="card-padded py-10 text-center">
              <p class="font-medium text-neutral-800 dark:text-neutral-100">
                No paid bundles match — but these free materials do
              </p>
              <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                Download them below, or try different keywords.
              </p>
              <a href="{{ route('search') }}" class="btn-secondary mt-5">Clear Filters</a>
            </div>
          @else
            <div class="card-padded py-16 text-center">
              <p class="font-medium text-neutral-800 dark:text-neutral-100">
                No past-question bundles found for your search.
              </p>
              <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                Try different keywords or clear the filters.
              </p>
              <a href="{{ route('search') }}" class="btn-secondary mt-5">Clear Filters</a>
            </div>
          @endif
        @else
          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($bundles as $bundle)
              {{-- Port of BundleCard --}}
              <div class="card card-hover group relative flex flex-col gap-2.5 overflow-hidden p-4 transition-all hover:-translate-y-0.5">
                <span aria-hidden="true" class="absolute inset-x-0 top-0 h-0.5 origin-left scale-x-0 bg-gradient-to-r from-brand-600 to-amber-400 transition-transform duration-300 group-hover:scale-x-100"></span>
                <div class="flex flex-wrap items-center gap-1.5">
                  <span class="badge-brand font-semibold">{{ $bundle->course->code ?? 'Bundle' }}</span>
                  <span class="badge-neutral">Level {{ $bundle->level }}</span>
                  <span class="badge-neutral">{{ $bundle->academic_year }}</span>
                  @if($bundle->solved)<span class="badge-success font-semibold">Solved</span>@endif
                </div>
                <a href="{{ route('bundles.show', $bundle->slug) }}"
                   class="line-clamp-2 font-semibold leading-snug text-neutral-900 transition-colors hover:text-brand-800 dark:text-white">
                  {{ $bundle->title }}
                </a>
                <p class="line-clamp-1 text-sm text-neutral-600 dark:text-neutral-300">
                  {{ $bundle->programme?->name }}@if($bundle->programme && $bundle->course) &middot; @endif{{ $bundle->course->title ?? '' }}
                </p>
                <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                  <div>
                    <p class="text-lg font-bold tracking-tight text-brand-800 dark:text-brand-300">GHS {{ $bundle->priceCedis() }}</p>
                    <p class="text-[11px] font-medium text-neutral-500 dark:text-neutral-400">
                      @if($bundle->resources->count() === 0)
                        Papers coming soon
                      @else
                        {{ $bundle->resources->count() }} {{ Str::plural('paper', $bundle->resources->count()) }} · one price
                      @endif
                    </p>
                  </div>
                </div>
                @if($bundle->resources->count() > 0)
                  <div class="mt-1 flex gap-2">
                    <a href="{{ route('bundles.show', $bundle->slug) }}"
                       class="btn-primary flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold text-white">Buy bundle</a>
                    <a href="{{ route('bundles.show', $bundle->slug) }}"
                       class="btn-secondary flex-1 rounded-lg px-3 py-2 text-center text-xs font-semibold">Preview</a>
                  </div>
                @endif
              </div>
            @endforeach
          </div>

          @if($bundles->lastPage() > 1)
            <nav class="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
              @foreach($bundles->getUrlRange(1, $bundles->lastPage()) as $p => $url)
                <a href="{{ $url }}"
                   class="grid h-9 w-9 place-items-center rounded-lg text-sm font-medium {{ $p == $bundles->currentPage()
                       ? 'bg-brand-700 text-white'
                       : 'bg-white text-neutral-700 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800' }}">
                  {{ $p }}
                </a>
              @endforeach
            </nav>
          @endif
        @endif

        {{-- ── Free downloadable materials — same filters, one place ── --}}
        @if($freeMaterials->isNotEmpty())
          <section class="mt-10 border-t border-neutral-200 pt-8 dark:border-neutral-800">
            <div class="flex items-baseline justify-between gap-2">
              <h2 class="text-base font-bold tracking-tight text-neutral-900 dark:text-white">Free study materials</h2>
              <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $freeMaterials->count() }} available</span>
            </div>
            <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">
              Slides, notes, and revision packs matching your search — download them free, no payment needed.
            </p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              @foreach($freeMaterials as $m)
                {{-- Port of FreeMaterialCard --}}
                <article class="card card-hover p-4 transition-all hover:-translate-y-0.5">
                  <div class="mb-3 flex flex-wrap items-center gap-1.5">
                    <span class="badge-brand">{{ $m->course->code ?? 'Material' }}</span>
                    <span class="badge-neutral">{{ $m->typeLabel() }}</span>
                    <span class="badge-gold">Free</span>
                  </div>
                  <h3 class="font-display text-base font-semibold leading-snug text-neutral-900 dark:text-white">{{ $m->title }}</h3>
                  <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                    {{ $m->programme?->name }} · Level {{ $m->level }} · {{ $m->semester === 1 ? 'First' : 'Second' }} semester
                  </p>
                  <div class="mt-4 flex items-center justify-between gap-2">
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                      @php $file = $m->files->first(); @endphp
                      {{ $file ? Format::bytes($file->size_bytes) : $m->page_count.' pages' }}
                    </span>
                    <a href="{{ route('materials.download', ['slug' => $m->slug]) }}"
                       class="btn-primary inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white">
                      Download
                    </a>
                  </div>
                </article>
              @endforeach
            </div>
          </section>
        @endif
      </div>
    </div>
  </div>
</main>
<script>
  (function () {
    var toggle = document.getElementById('filters-toggle');
    if (!toggle) return;
    toggle.addEventListener('click', function () {
      var body = document.getElementById('filters-body');
      var chevron = document.getElementById('filters-chevron');
      var closed = body.classList.toggle('filters-closed');
      chevron.classList.toggle('rotate-180', !closed);
      toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
    });
  })();
</script>
@endsection
