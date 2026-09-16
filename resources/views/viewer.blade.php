@extends('layouts.app')

@section('title', $resource->title.' — Secure viewer')

{{-- Reading is the task here, so this page drops the site chrome
     (header + bottom nav) and gives the paper the whole viewport. --}}
@section('immersive')@endsection
@section('body-class', 'is-reader')

{{--
    The reader.

    The paper is drawn to <canvas> by PDF.js (public/js/pdf-reader.js).
    Nothing leaves the shell: no browser PDF viewer, so no download /
    print / share menu, and pinch-zoom behaves like it does anywhere else
    on the page — which is also what makes it readable on Chrome for
    Android, where in-page PDFs are blocked outright.

    States, worst case first:

      #pdf-fallback  server-rendered card — the no-JS path, and where
                     every failure lands (viewer.open always works)
      #pdf-loading   shown while the document and its pages come in
      #pdf-reader    the canvas reader, revealed once the page can draw

    Visibility is toggled with `.is-hidden` rather than Tailwind's
    `hidden`, because the shell is a flex column and a display utility
    would fight the layout.

    Nothing here ever links to the original file: the bytes come from the
    signed document endpoint, per-user watermarked.
--}}
@php
    $minutes = max(1, (int) round(config('viewer.url_ttl_seconds') / 60));
@endphp

@section('content')
    <div class="reader-shell min-h-[100dvh] bg-neutral-100 dark:bg-neutral-950">
        {{-- Only what a reader needs: back, what they are reading, and
             full screen. Themes stay reachable so a long read can switch. --}}
        <div id="pdf-bar" class="sticky top-0 z-30 flex items-center gap-1.5 border-b border-neutral-200 bg-white/95 px-2 py-2 backdrop-blur-md sm:px-3 dark:border-neutral-800 dark:bg-neutral-900/95">
            <a href="{{ route('library') }}" title="Back to My Library" aria-label="Back to My Library"
               class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            </a>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-neutral-900 dark:text-neutral-100">{{ $resource->title }}</p>
                <p class="truncate text-[11px] text-neutral-500 dark:text-neutral-400">
                    Licensed copy · your identity is embedded · the link renews every {{ $minutes }} minutes
                </p>
            </div>

            <button type="button" onclick="cycleTheme(this)" title="Theme" aria-label="Toggle theme"
                    class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                <svg class="theme-icon-sun hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                <svg class="theme-icon-moon hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>

            <a href="{{ route('viewer.open', $resource->slug) }}" target="_blank" rel="noopener"
               title="Open in a new tab" aria-label="Open in a new tab"
               class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-neutral-600 transition hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </a>

        </div>

        {{-- Loading: the reader takes the fallback card down while it tries. --}}
        <div id="pdf-loading" class="is-hidden flex items-center gap-2 px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300" role="status" aria-live="polite">
            <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-neutral-300 border-t-brand-600 dark:border-neutral-600 dark:border-t-brand-400" aria-hidden="true"></span><span id="pdf-loading-text">Loading your watermarked copy…</span>
        </div>

        {{-- Failure: both ways out. --}}
        <div id="pdf-error" class="is-hidden px-4 py-3" role="alert">
            <p id="pdf-error-text" class="text-sm text-neutral-600 dark:text-neutral-300"></p>
            <button type="button" id="pdf-retry" class="btn-secondary btn-sm mt-2">Try again</button>
        </div>

        <div id="pdf-reader" class="is-hidden bg-neutral-100 dark:bg-neutral-950"
                 data-document="{{ $documentUrl }}"
                 data-refresh="{{ route('viewer.document-url', $resource->slug) }}"
                 data-worker="{{ asset('vendor/pdfjs/pdf.worker.min.js') }}">
            {{-- Sticks under the title bar while the document scrolls, so
                 paging and zoom are always in reach. --}}
            <div data-pdf-toolbar
                 class="sticky z-20 flex items-center justify-between gap-2 border-b border-neutral-200 bg-white/95 px-2 py-1.5 backdrop-blur-md dark:border-neutral-800 dark:bg-neutral-900/95">
                <div class="flex items-center gap-1.5">
                    <button type="button" data-pdf-prev aria-label="Previous page"
                                class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <button type="button" data-pdf-next aria-label="Next page"
                                class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 disabled:opacity-40 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                        <span data-pdf-status class="ml-1 text-xs font-medium text-neutral-600 dark:text-neutral-300" aria-live="polite">Page 1 of 1</span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button type="button" data-pdf-zoom-out aria-label="Zoom out"
                                class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>
                        </button>
                        <button type="button" data-pdf-zoom-reset
                                class="h-8 rounded-md border border-neutral-200 bg-white px-2 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                aria-label="Reset zoom to fit the width">
                            <span data-pdf-zoom-label>100%</span>
                        </button>
                        <button type="button" data-pdf-zoom-in aria-label="Zoom in"
                                class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        </button>

                        {{-- Real, OS-level full screen where the browser allows it
                             (Chrome on Android, iPadOS, desktop). It lives INSIDE the
                             reader on purpose: the browser hides everything else in
                             full screen, so an exit control anywhere else would
                             strand the student until they knew to press Escape. --}}
                        <button type="button" id="pdf-fullscreen" title="Full screen" aria-label="Full screen"
                                class="is-hidden ml-0.5 grid h-8 w-8 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-600 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800">
                            <svg class="pdf-icon-enter h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
                            <svg class="pdf-icon-exit hidden h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>
                        </button>
                    </div>
                </div>

            {{-- Pages stack in normal flow: the *document* scrolls
                 vertically, the way a phone swipe and a scroll wheel both
                 expect, instead of trapping the paper in an inner pane. --}}
            <div data-pdf-pages tabindex="0" role="group" aria-label="Document pages"
                 class="pdf-pages p-3 outline-none"></div>
        </div>

        {{-- No JavaScript, no library, or a failure: the student still
             gets their paper, through viewer.open (which re-runs the
             whole authorization chain and mints a fresh signed URL). --}}
        <div id="pdf-fallback" class="p-4">
            <div class="card-padded mx-auto max-w-md text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-700 ring-1 ring-brand-100 dark:bg-brand-950 dark:text-brand-300 dark:ring-brand-900">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7Z"/>
                        <path d="M14 2v5h5"/>
                        <path d="M9 15h6"/>
                    </svg>
                </span>
                <h2 class="mt-4 text-base font-semibold text-neutral-900 dark:text-white">Open the paper in your reader</h2>
                <p class="mx-auto mt-1 text-sm leading-relaxed text-neutral-600 dark:text-neutral-300">
                    This browser isn't running our in-page reader — JavaScript may be off, or the page may not have
                    fully loaded. Your paper opens in your phone's PDF reader instead, still stamped with your account.
                </p>
                <a href="{{ route('viewer.open', $resource->slug) }}" target="_blank" rel="noopener" class="btn-primary mt-5">
                    Open the document
                </a>
            </div>
        </div>
    </div>
@endsection

{{-- Styles for what the reader script creates, plus the immersive shell.
     Page boxes are built in JS, so their look lives here rather than in
     dynamic utility classes Tailwind would never see. --}}
@push('scripts')
<style>
    /* Standalone visibility helper: `hidden` is a display utility, and the
       shell needs display:flex when it is shown. */
    .is-hidden { display: none !important; }

    /* Immersive shell: the paper owns the page. Vertical scrolling is the
       document's own — no nested scroll pane — so a phone swipe, a scroll
       wheel, keyboard paging and the browser's pull-to-refresh all behave
       natively. */
    /* No overflow on the shell: an ancestor with overflow (even just
       overflow-x) becomes the sticky bars' scroll container, and they
       would scroll away with the document instead of staying put. */
    body.is-reader main { max-width: none; padding: 0; }
    /* A downward swipe at the top of the paper should not pull-to-refresh
       the reader out from under a student mid-page. */
    body.is-reader { overscroll-behavior-y: contain; }
    /* Reserve the window's scrollbar up front: the fit-width scale is
       measured from the page's width, and a scrollbar appearing after the
       first page renders would otherwise push the pages out by its width. */
    html { scrollbar-gutter: stable; }

    /* True full screen (Chrome on Android, iPadOS, desktop): browser UI
       goes too, and the fullscreen element becomes the scroller. */
    #pdf-reader:fullscreen { overflow: auto; background: #f5f5f5; }
    .dark #pdf-reader:fullscreen { background: #0a0a0a; }

    /* The toolbar parks under the title bar (whose height the reader script
       measures into --reader-bar-h) — and at the very top in full screen,
       where the title bar is not shown at all. */
    [data-pdf-toolbar] { top: var(--reader-bar-h, 3.5rem); }
    #pdf-reader:fullscreen [data-pdf-toolbar] { top: 0; }

    .pdf-pages { scrollbar-width: thin; }
    .pdf-page {
        position: relative;
        margin: 0 auto 0.75rem;
        background: #fff;
        border-radius: 2px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .16), 0 12px 28px -18px rgba(0, 0, 0, .45);
        overflow: hidden;
    }
    .pdf-page:last-child { margin-bottom: 0; }
    .pdf-page canvas { display: block; user-select: none; -webkit-user-select: none; }
    .pdf-page-label {
        position: absolute;
        inset: 0;
        display: grid;
        place-items: center;
        font-size: 12px;
        font-variant-numeric: tabular-nums;
        color: #a3a3a3;
    }
</style>
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script src="{{ asset('js/pdf-reader.js') }}?v={{ filemtime(public_path('js/pdf-reader.js')) }}"></script>
@endpush
