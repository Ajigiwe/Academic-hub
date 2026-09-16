{{-- Port of the Next.js BrowseFlow component (browse-flow.tsx): the
     three-step guided flow — programme → level → semester. Server-driven
     via links instead of client state; each choice navigates immediately.
     Expects: $base (route name or path), $programmes (collection with
     slug/name/bundles/materials), $programme, $level, $semester.

     Optional: $stepCounts — [['level' => [100 => ['bundles' => 2,
     'materials' => 1]], 'semester' => [1 => [...]]]]. When supplied (see
     App\Services\ContentCounts) each level/semester card reports what
     sits behind it, so nobody drills three steps into an empty
     semester. Omitted → cards render exactly as before. --}}
@php
    $flowStep = $programme ? ($level ? 2 : 1) : 0;
    $stepCounts = $stepCounts ?? [];

    // "2 bundles · 1 free material" for a card, or "Nothing here yet"
    // when that selection holds no openable content. Null = this page
    // doesn't report counts for that step at all.
    $countLine = function (string $step, int $value) use ($stepCounts) {
        $counts = $stepCounts[$step][$value] ?? null;
        if ($counts === null) {
            return null;
        }
        $bundles = (int) ($counts['bundles'] ?? 0);
        $materials = (int) ($counts['materials'] ?? 0);
        $parts = collect([
            $bundles > 0 ? $bundles.' '.Str::plural('bundle', $bundles) : null,
            $materials > 0 ? $materials.' free '.Str::plural('material', $materials) : null,
        ])->filter();

        return [
            'text' => $parts->isEmpty() ? 'Nothing here yet' : $parts->implode(' · '),
            'empty' => $parts->isEmpty(),
        ];
    };
    $semesters = [
        ['value' => 1, 'label' => 'First Semester', 'sub' => 'Sept – Dec'],
        ['value' => 2, 'label' => 'Second Semester', 'sub' => 'Jan – May'],
    ];
    $yearLabels = [100 => 'First year', 200 => 'Second year', 300 => 'Third year', 400 => 'Fourth year'];
@endphp

<div class="card-padded">
    {{-- ── Step indicator ─────────────────────────────────────────── --}}
    <ol class="flex items-center gap-2" aria-label="Browse steps">
        @foreach (['Programme', 'Level', 'Semester'] as $i => $label)
            @php $state = $i < $flowStep ? 'done' : ($i === $flowStep ? 'current' : 'todo'); @endphp
            <li class="flex flex-1 items-center gap-2">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold transition
                             {{ match ($state) {
                                 'done' => 'bg-brand-700 text-white',
                                 'current' => 'bg-brand-700 text-white ring-4 ring-brand-100 dark:ring-brand-950',
                                 default => 'bg-neutral-100 text-neutral-400 dark:bg-neutral-800 dark:text-neutral-500',
                             } }}"
                      @if($state === 'current') aria-current="step" @endif>
                    @if ($state === 'done')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        <span class="sr-only">{{ $label }} — done</span>
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>
                <span class="hidden text-xs sm:block {{ $state === 'current' ? 'font-semibold text-neutral-900 dark:text-white' : ($state === 'done' ? 'font-medium text-neutral-600 dark:text-neutral-300' : 'text-neutral-400 dark:text-neutral-500') }}">
                    {{ $label }}
                </span>
                @if ($i < 2)
                    <span class="h-px flex-1 {{ $i < $flowStep ? 'bg-brand-600' : 'bg-neutral-200 dark:bg-neutral-700' }}" aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- ── Step 1 — programme ─────────────────────────────────────── --}}
    @if ($flowStep === 0)
        <div class="mt-6">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-700 dark:text-brand-300">Step 1 of 3</p>
            <h2 class="mt-1 text-lg font-bold tracking-tight text-neutral-900 dark:text-white">Select your programme</h2>
            <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">The track you're enrolled on determines the courses shown.</p>
            <div class="mt-4 grid gap-3 {{ $programmes->count() === 1 ? 'sm:max-w-md' : ($programmes->count() === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3') }}">
                @foreach ($programmes as $p)
                    @php $hasContent = $p['bundles'] > 0 || $p['materials'] > 0; @endphp
                    <a href="{{ route($base, ['programme' => $p['slug']]) }}"
                       class="group relative flex flex-col rounded-2xl border border-neutral-200 bg-white p-4 text-left transition duration-150 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-brand-500">
                        <span class="flex items-start justify-between gap-2">
                            <span class="inline-block rounded-md bg-neutral-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">{{ $p['slug'] }}</span>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600 dark:text-neutral-600 dark:group-hover:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </span>
                        <span class="mt-2 block text-sm font-semibold leading-snug text-neutral-900 dark:text-white">{{ $p['name'] }}</span>
                        <span class="mt-2 flex items-center gap-1.5 text-xs font-medium {{ $hasContent ? 'text-neutral-600 dark:text-neutral-300' : 'text-neutral-400 dark:text-neutral-500' }}">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $hasContent ? 'bg-emerald-500' : 'bg-neutral-300 dark:bg-neutral-600' }}" aria-hidden="true"></span>
                            <span>
                                @if ($hasContent)
                                    {{ collect([$p['bundles'] > 0 ? $p['bundles'].' '.Str::plural('bundle', $p['bundles']) : null, $p['materials'] > 0 ? $p['materials'].' free '.Str::plural('material', $p['materials']) : null])->filter()->implode(' · ') }}
                                @else
                                    No content yet
                                @endif
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Step 2 — level ─────────────────────────────────────────── --}}
    @if ($flowStep === 1)
        <div class="mt-6">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-700 dark:text-brand-300">Step 2 of 3</p>
            <h2 class="mt-1 text-lg font-bold tracking-tight text-neutral-900 dark:text-white">Select your level</h2>
            <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">Pick the year you're in to see its papers and materials.</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach (\App\Models\Programme::LEVELS as $l)
                    @php $line = $countLine('level', $l); @endphp
                    <a href="{{ route($base, ['programme' => $programme, 'level' => $l]) }}"
                       class="group relative flex flex-col rounded-2xl border p-4 transition duration-150 hover:-translate-y-0.5 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 {{ $line && $line['empty'] ? 'border-neutral-200 border-dashed bg-neutral-50/60 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/40 dark:hover:border-neutral-700' : 'border-neutral-200 bg-white hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-brand-500' }}">
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-display text-2xl font-bold leading-none tracking-tight {{ $line && $line['empty'] ? 'text-neutral-400 dark:text-neutral-500' : 'text-neutral-900 dark:text-white' }}">{{ $l }}</span>
                            <svg class="h-4 w-4 shrink-0 transition group-hover:translate-x-0.5 {{ $line && $line['empty'] ? 'text-neutral-300 dark:text-neutral-700' : 'text-neutral-300 group-hover:text-brand-600 dark:text-neutral-600 dark:group-hover:text-brand-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </span>
                        <span class="mt-1.5 text-[11px] font-semibold uppercase tracking-widest text-neutral-500 dark:text-neutral-400">{{ $yearLabels[$l] }}</span>
                        @if ($line)
                            <span class="mt-3 flex items-center gap-1.5 text-[11px] font-medium {{ $line['empty'] ? 'text-neutral-400 dark:text-neutral-500' : 'text-neutral-600 dark:text-neutral-300' }}">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $line['empty'] ? 'bg-neutral-300 dark:bg-neutral-600' : 'bg-emerald-500' }}" aria-hidden="true"></span>
                                <span>{{ $line['text'] }}</span>
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Step 3 — semester ──────────────────────────────────────── --}}
    @if ($flowStep === 2 && ! $semester)
        <div class="mt-6">
            <p class="text-[11px] font-bold uppercase tracking-widest text-brand-700 dark:text-brand-300">Step 3 of 3</p>
            <h2 class="mt-1 text-lg font-bold tracking-tight text-neutral-900 dark:text-white">Select your semester</h2>
            <p class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-300">Content is tagged by semester — pick the one you're studying.</p>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
                @foreach ($semesters as $s)
                    @php $line = $countLine('semester', $s['value']); @endphp
                    <a href="{{ route($base, ['programme' => $programme, 'level' => $level, 'semester' => $s['value']]) }}"
                       class="group relative flex flex-col rounded-2xl border p-4 transition duration-150 hover:-translate-y-0.5 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 {{ $line && $line['empty'] ? 'border-neutral-200 border-dashed bg-neutral-50/60 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/40 dark:hover:border-neutral-700' : 'border-neutral-200 bg-white hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-brand-500' }}">
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-sm font-bold {{ $line && $line['empty'] ? 'text-neutral-500 dark:text-neutral-400' : 'text-neutral-900 dark:text-white' }}">{{ $s['label'] }}</span>
                            <svg class="mt-0.5 h-4 w-4 shrink-0 transition group-hover:translate-x-0.5 {{ $line && $line['empty'] ? 'text-neutral-300 dark:text-neutral-700' : 'text-neutral-300 group-hover:text-brand-600 dark:text-neutral-600 dark:group-hover:text-brand-400' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </span>
                        <span class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $s['sub'] }}</span>
                        @if ($line)
                            <span class="mt-3 flex items-center gap-1.5 text-xs font-medium {{ $line['empty'] ? 'text-neutral-400 dark:text-neutral-500' : 'text-neutral-600 dark:text-neutral-300' }}">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $line['empty'] ? 'bg-neutral-300 dark:bg-neutral-600' : 'bg-emerald-500' }}" aria-hidden="true"></span>
                                <span>{{ $line['text'] }}</span>
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ── Controls ───────────────────────────────────────────────── --}}
    <div class="mt-6 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
        @if ($flowStep === 0)
            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ $programmes->count() }} {{ Str::plural('programme', $programmes->count()) }} available
            </span>
        @else
            <a href="{{ route($base) }}" class="btn-secondary">← Back</a>
        @endif
    </div>
</div>
