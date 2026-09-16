@extends('layouts.app')

@section('title', 'Past questions — Academic Hub')

@section('content')
<main class="flex-1">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    {{-- Breadcrumb + context (port of courses/page.tsx) --}}
    <nav class="text-sm text-neutral-500 dark:text-neutral-400" aria-label="Breadcrumb">
      <a href="{{ route('home') }}" class="transition-colors hover:text-brand-700 dark:hover:text-brand-300">Home</a>
      <span class="mx-1.5 text-neutral-300 dark:text-neutral-600">/</span>
      <span class="text-neutral-700 dark:text-neutral-300">Past questions</span>
    </nav>

    <div class="mt-3">
      <h1 class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-white">
        @if($guided)
          {{ $chips->implode(' · ') }}
        @else
          Past questions
        @endif
      </h1>
      <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
        @if($guided)
          {{ $bundles->count() }} course {{ Str::plural('bundle', $bundles->count()) }} for sale, plus free materials — one price unlocks every paper in a bundle.
        @else
          Tell us your programme, level, and semester — we'll show your library.
        @endif
      </p>
    </div>

    {{-- The 3-step flow. Once all three are committed the flow collapses
         into a summary bar and the library appears below. --}}
    <div class="mt-6">
      @include('partials.browse-flow', ['base' => 'courses'])
    </div>

    @if(!$guided)
      <div class="card-padded mt-6 py-12 text-center">
        <p class="font-medium text-neutral-800 dark:text-neutral-100">Complete the three steps above</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-300">
          @if($anyFilter)
            One more selection and we'll show the bundles for your course.
          @else
            Your programme, level, and semester — then the library opens.
          @endif
        </p>
      </div>
    @else
      @if($bundles->isNotEmpty())
        {{-- Paid bundles — grouped by course (port of the original grouping) --}}
        <section class="mt-8">
          <h2 class="text-lg font-bold tracking-tight text-neutral-900 dark:text-white">Past-question bundles for sale</h2>
          <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Buy one bundle per course and unlock every paper inside it.</p>
          @foreach($bundles->groupBy(fn ($b) => $b->course->code ?? 'Other') as $code => $group)
            <div class="mt-6">
              <div class="flex items-baseline gap-2">
                <h3 class="font-semibold text-neutral-900 dark:text-white">{{ $code }}</h3>
                <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $group->first()->course->title ?? '' }}</span>
              </div>
              <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($group as $bundle)
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
            </div>
          @endforeach
        </section>
      @else
        <section class="card-padded mt-8 py-12 text-center">
          <p class="font-medium text-neutral-800 dark:text-neutral-100">No past-question bundles here yet</p>
          <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-300">
            Papers for {{ $chips->implode(' · ') }} are on the way — free materials below, or check back soon.
          </p>
        </section>
      @endif

      @if($freeMaterials->isNotEmpty())
        <section class="mt-12">
          <div class="flex items-baseline justify-between gap-2">
            <h2 class="text-lg font-bold tracking-tight text-neutral-900 dark:text-white">Free study materials</h2>
            <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $freeMaterials->count() }} available</span>
          </div>
          <p class="mt-0.5 text-sm text-neutral-500 dark:text-neutral-400">Slides, notes, and revision packs for your selection — free to download.</p>
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

      @if($bundles->isEmpty() && $freeMaterials->isEmpty())
        <p class="mt-8 text-center text-sm text-neutral-500 dark:text-neutral-400">Nothing here yet — try another semester or level.</p>
      @endif
    @endif
  </div>
</main>
@endsection
