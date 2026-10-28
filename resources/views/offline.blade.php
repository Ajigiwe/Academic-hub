@extends('layouts.app')

@section('title', 'Academic Hub — Offline')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center py-12 px-4 sm:px-6">

    <div class="text-center">
        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 bg-neutral-100 dark:bg-neutral-800">
            <svg class="h-10 w-10 text-neutral-400 dark:text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 1L3 5v11c0 5 9 22 9-3 0-2-.5-3.5-3-3V5a2 2 0 0 0-4 0 2 2 0 0 0-2 2v2c0 5 9 12 9 3-3 0-3.5-2-3-3V5a2 2 0 0 0-4 0 2 2 0 0 0-2 2v2c0 5 9 12 9 3 0-2.5 1.5-3.5 3-3Z"/>
                <circle cx="12" cy="12" r="1"/>
            </svg>
        </div>

        <h1 class="mb-4 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">
            You're offline
        </h1>

        <p class="mb-6 text-neutral-600 dark:text-neutral-400">
            No internet connection is available. Your library is still accessible
            for resources you've already loaded.
        </p>

        <a href="{{ route('library') }}" class="btn-primary">
            View Library
        </a>
    </div>

</section>
@endsection