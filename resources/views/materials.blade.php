@extends('layouts.app')

@section('title', 'Course materials — Academic Hub')

@section('content')
<main class="flex-1">
  <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    {{-- Breadcrumb + context (port of materials/page.tsx) --}}
    <nav class="text-sm text-neutral-500 dark:text-neutral-400" aria-label="Breadcrumb">
      <a href="{{ route('home') }}" class="transition-colors hover:text-brand-700 dark:hover:text-brand-300">Home</a>
      <span class="mx-1.5 text-neutral-300 dark:text-neutral-600">/</span>
      <span class="text-neutral-700 dark:text-neutral-300">Course materials</span>
    </nav>

    <div class="mt-3">
      <h1 class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-white">
        @if($guided)
          {{ $chips->implode(' · ') }}
        @else
          Course materials
        @endif
      </h1>
      <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
        @if($guided)
          {{ $materials->count() }} free download{{ Str::plural('', $materials->count()) }} for your selection — slides, lecture notes, and revision packs.
        @else
          Tell us your programme, level, and semester — we'll show your library.
        @endif
      </p>
    </div>

    {{-- The 3-step flow (shared partial, same as /courses) --}}
    <div class="mt-6">
      @include('partials.browse-flow', ['base' => 'materials'])
    </div>

    @if($guided)
      @if($materials->isEmpty())
        <div class="card-padded mt-6 py-12 text-center">
          <p class="font-medium text-neutral-800 dark:text-neutral-100">No materials for this selection yet</p>
          <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-300">
            Slides and notes for {{ $chips->implode(' · ') }} are being prepared — check back soon, or look at past questions instead.
          </p>
          <div class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="{{ route('courses') }}" class="btn-primary">Looking for past questions instead?</a>
          </div>
        </div>
      @else
        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          @foreach($materials as $m)
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
                  {{ $file ? \App\Support\Format::bytes($file->size_bytes) : $m->page_count.' pages' }}
                </span>
                <a href="{{ route('materials.download', ['slug' => $m->slug]) }}"
                   class="btn-primary inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white">
                  Download
                </a>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    @else
      <div class="card-padded mt-6 py-12 text-center">
        <p class="font-medium text-neutral-800 dark:text-neutral-100">Complete the three steps above</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-300">
          Your programme, level, and semester — then the free materials library opens.
        </p>
      </div>
    @endif
  </div>
</main>
@endsection
