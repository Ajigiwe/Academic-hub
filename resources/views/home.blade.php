@extends('layouts.app')

@section('title', 'Academic Hub — Find. Purchase. Study.')

@section('content')
    {{-- Hero + intent entry — port of the Next.js homepage: two doors,
         the visitor picks their intent immediately. --}}
    <section class="relative -mx-4 overflow-hidden border-b border-neutral-200 sm:-mx-6 dark:border-neutral-800">
        <div class="absolute inset-0 bg-gradient-to-b from-brand-50 via-white to-neutral-50 dark:from-brand-950 dark:via-neutral-950 dark:to-neutral-950"></div>
        <div class="absolute inset-0 opacity-35" style="background-image: radial-gradient(circle at 15% 20%, rgb(37 99 235 / 0.08) 0, transparent 40%), radial-gradient(circle at 85% 10%, rgb(11 45 91 / 0.08) 0, transparent 45%);" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-16 lg:py-20">
            <div class="text-center">
                <h1 class="mx-auto max-w-2xl text-4xl font-extrabold tracking-[-0.035em] text-neutral-900 sm:text-6xl dark:text-neutral-100">
                    Find the Past Questions
                    <span class="bg-gradient-to-r from-brand-700 to-brand-500 bg-clip-text text-transparent dark:from-brand-400 dark:to-brand-300">You Need.</span>
                </h1>
                <p class="mx-auto mt-4 max-w-xl text-base text-neutral-600 sm:text-lg dark:text-neutral-400">
                    Solved &amp; unsolved past questions for your course and year, plus free slides and notes — tell us what you came for and we'll take you there.
                </p>

                <div class="mx-auto mt-8 grid max-w-2xl gap-4 sm:grid-cols-2 sm:gap-5">
                    {{-- Door 1: past questions (for sale) --}}
                    <a href="{{ route('courses') }}" class="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all duration-200 hover:-translate-y-1 hover:border-brand-500 hover:shadow-lift sm:p-7 dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand-500/15 opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div>
                        <span class="relative grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white shadow-card ring-1 ring-white/25 transition-transform duration-200 group-hover:-rotate-3 group-hover:scale-105">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/></svg>
                        </span>
                        <p class="relative mt-4 text-lg font-bold text-neutral-900 dark:text-neutral-100">Past questions</p>
                        <p class="relative mt-1 text-sm text-neutral-600 dark:text-neutral-400">Solved &amp; unsolved papers by course &amp; year — for sale.</p>
                        <span class="relative mt-5 flex items-center justify-between">
                            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700 dark:bg-brand-950 dark:text-brand-300">For sale</span>
                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 transition-transform duration-200 group-hover:translate-x-1 dark:text-brand-400" aria-hidden="true">Browse papers →</span>
                        </span>
                    </a>

                    {{-- Door 2: free course materials --}}
                    <a href="{{ route('materials') }}" class="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all duration-200 hover:-translate-y-1 hover:border-amber-400 hover:shadow-lift sm:p-7 dark:border-neutral-800 dark:bg-neutral-900">
                        <div class="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-amber-400/20 opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div>
                        <span class="relative grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-card ring-1 ring-white/25 transition-transform duration-200 group-hover:rotate-3 group-hover:scale-105">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h10M7 12h10M7 16h6"/></svg>
                        </span>
                        <p class="relative mt-4 text-lg font-bold text-neutral-900 dark:text-neutral-100">Course materials</p>
                        <p class="relative mt-1 text-sm text-neutral-600 dark:text-neutral-400">Slides &amp; notes — free to download.</p>
                        <span class="relative mt-5 flex items-center justify-between">
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Free</span>
                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">Browse materials →</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </section>
@endsection
