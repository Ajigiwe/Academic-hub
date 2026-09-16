@extends('layouts.app')

@section('title', 'Saved items — Academic Hub')

@section('content')
    <h1 class="text-2xl font-bold tracking-tight">Saved items</h1>
    <p class="mt-1 text-sm text-neutral-600">Papers and free materials you've bookmarked.</p>

    <ul class="mt-6 divide-y divide-neutral-100 rounded-lg border border-neutral-200 bg-white">
        @forelse ($favourites as $favourite)
            @php($r = $favourite->resource)
            <li class="flex items-center justify-between px-4 py-3 text-sm">
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $r->title }}</div>
                    <div class="text-xs text-neutral-500">
                        {{ $r->course->code }}
                        @if ($r->bundle_id) · paid paper @else · free material @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if ($r->bundle_id)
                        <a href="{{ route('viewer.show', $r->slug) }}"
                           class="rounded-md bg-brand-700 px-3 py-1 text-xs font-medium text-white hover:bg-brand-800">Read</a>
                    @else
                        <a href="{{ route('materials.download', $r->slug) }}"
                           class="rounded-md border border-brand-600 px-3 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50">Download</a>
                    @endif
                    <form method="POST" action="{{ route('favourites.toggle', $r) }}">
                        @csrf
                        <button class="text-xs text-neutral-400 hover:text-red-600" title="Remove from saved items">Remove</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="px-4 py-10 text-center text-sm text-neutral-500">
                Nothing saved yet — look for the ☆ save button in your library.
            </li>
        @endforelse
    </ul>
@endsection
