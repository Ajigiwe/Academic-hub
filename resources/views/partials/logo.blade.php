{{-- Port of src/components/logo.tsx — the Academic Hub mark (AH monogram
     + waves) and wordmark. `light` flips to all-white for dark surfaces. --}}
@php
    $light = $light ?? false;
    $tagline = $tagline ?? false;
    $body = $light ? '#ffffff' : '#24417a';
    $accent = $light ? '#ffffff' : '#2563eb';
    $navy = $light ? '#ffffff' : '#0b2d5b';
@endphp

<span class="flex items-center gap-2.5">
    <svg viewBox="0 0 120 132" fill="none" class="h-9 w-9 shrink-0" aria-hidden="true">
        <g stroke-linecap="round" stroke-linejoin="round">
            {{-- A left leg --}}
            <path d="M40 30 L16 90" stroke="{{ $body }}" stroke-width="10"/>
            {{-- A right leg / H left stem (shared) --}}
            <path d="M40 30 L40 90" stroke="{{ $body }}" stroke-width="10"/>
            {{-- A crossbar (accent) --}}
            <path d="M24 64 L44 64" stroke="{{ $accent }}" stroke-width="8.5"/>
            {{-- H right stem (taller) --}}
            <path d="M72 22 L72 90" stroke="{{ $navy }}" stroke-width="11"/>
            {{-- H crossbar --}}
            <path d="M40 58 L72 58" stroke="{{ $body }}" stroke-width="8.5"/>
            {{-- waves --}}
            <path d="M8 104 C26 97 44 111 62 102" stroke="{{ $accent }}" stroke-width="5"/>
            <path d="M18 114 C34 108 52 120 70 111" stroke="{{ $navy }}" stroke-width="4"/>
        </g>
    </svg>
    <span class="flex flex-col leading-tight">
        <span class="text-[15px] font-bold tracking-tight">
            <span style="color: {{ $light ? '#ffffff' : '#0b2d5b' }};">Academic</span>
            <span style="color: {{ $light ? '#ffffff' : '#2563eb' }};">Hub</span>
        </span>
        @if ($tagline)
            {{-- Hidden on phones — same overflow reason as the original. --}}
            <span class="hidden text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-600 min-[480px]:block">Past Questions. Smarter Preparation.</span>
        @endif
    </span>
</span>
