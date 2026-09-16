@extends('admin.layout')

@section('title', 'Edit paper — Admin')

@section('admin-content')
    <a href="{{ route('admin.papers') }}" class="text-sm font-medium text-neutral-500 hover:text-brand-800">← Back to papers</a>

    {{-- ── Header ─────────────────────────────────────────────────── --}}
    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold text-neutral-900">{{ $paper->title }}</h1>
                <span class="{{ match ($paper->status) {
                    'PUBLISHED' => 'badge-success',
                    'UNPUBLISHED' => 'badge-gold',
                    'ARCHIVED' => 'badge-danger',
                    default => 'badge-neutral',
                } }}">{{ $paper->status }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-600">
                <span class="badge-brand mr-2">{{ $paper->course?->code ?? '—' }}</span>
                {{ $paper->course?->title }} · Level {{ $paper->level }} · Sem {{ $paper->semester }} · {{ $paper->academic_year }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if ($paper->bundle)
                <a href="{{ route('bundles.show', $paper->bundle->slug) }}" class="btn-secondary btn-sm">View bundle →</a>
            @endif
            @if ($paper->status === 'PUBLISHED' && $paper->bundle)
                <a href="{{ route('bundles.show', $paper->bundle->slug) }}" class="btn-secondary btn-sm">View paper →</a>
            @endif
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-5">
        {{-- ── Metadata form (wider column) ───────────────────────── --}}
        <section class="card p-5 lg:col-span-3">
            <h2 class="text-base font-semibold text-neutral-900">Catalog details</h2>
            <p class="mt-1 mb-4 text-sm text-neutral-600">
                Description, type, and programme are editable after creation. Course, level, semester,
                year, and bundle are fixed so existing orders keep their meaning.
            </p>
            <form method="POST" action="{{ route('admin.papers.update', $paper) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="edit-type">Type</label>
                    <select id="edit-type" name="type" class="input text-sm">
                        @foreach (['PAST_QUESTION' => 'Past question', 'LECTURE_NOTES' => 'Lecture notes', 'SLIDES' => 'Slides', 'REVISION' => 'Revision', 'PRACTICE' => 'Practice'] as $value => $label)
                            <option value="{{ $value }}" @selected($paper->type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="edit-programme">Programme</label>
                    <input id="edit-programme" name="programme_name" required minlength="2" maxlength="120"
                           value="{{ $paper->programme->name }}" list="edit-programme-names"
                           placeholder="BSc IT" class="input text-sm">
                    <datalist id="edit-programme-names">
                        @foreach ($programmes as $name)
                            <option value="{{ $name }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="label" for="edit-description">Description</label>
                    <textarea id="edit-description" name="description" rows="4" maxlength="2000"
                              placeholder="What students get — coverage, format, solutions included, etc."
                              class="input text-sm">{{ $paper->description }}</textarea>
                </div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary">Save changes</button>
                    <p class="text-xs text-neutral-500">Catalog pages update immediately after saving.</p>
                </div>
            </form>
        </section>

        {{-- ── Right column: file version + stats ─────────────────── --}}
        <div class="space-y-6 lg:col-span-2">
            <section class="card p-5">
                <h2 class="text-base font-semibold text-neutral-900">Document file</h2>
                <div class="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:!bg-neutral-900">
                    @if ($currentFile)
                        <p class="flex items-center gap-2 text-sm font-medium text-neutral-900">
                            <span class="badge-success">PDF</span>
                            <span class="truncate">{{ $currentFile->original_name }}</span>
                        </p>
                        <p class="mt-1.5 text-xs text-neutral-600">
                            {{ \App\Support\Format::bytes($currentFile->size_bytes) }} ·
                            {{ $paper->page_count ?? '?' }} pages · uploaded {{ $currentFile->created_at->format('d/m/Y') }}
                        </p>
                    @else
                        <p class="text-sm text-neutral-600">No file attached yet — upload one below to publish.</p>
                    @endif
                </div>

                <h3 class="mt-5 mb-2 text-sm font-semibold text-neutral-900">Upload a replacement version</h3>
                <form method="POST" action="{{ route('admin.papers.replace', $paper) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input name="file" type="file" accept="application/pdf,.pdf" required
                           class="block w-full cursor-pointer rounded-lg border border-neutral-300 bg-white text-sm">
                    <p class="text-xs text-neutral-500">
                        Max 30 MB · not password-protected. The new file goes through the same server-side
                        sniffing and probing as the first upload.
                    </p>
                    <button type="submit" class="btn-secondary">Upload new version</button>
                </form>

                @if ($paper->files->count() > 1)
                    <div class="mt-5 border-t border-neutral-200 pt-4">
                        <h4 class="text-[11px] font-bold uppercase tracking-wide text-neutral-500">
                            Version history ({{ $paper->files->count() }})
                        </h4>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($paper->files as $file)
                                <li class="flex items-center justify-between text-xs text-neutral-600">
                                    <span class="truncate">
                                        {{ $file->created_at->format('d/m/Y') }} · {{ \App\Support\Format::bytes($file->size_bytes) }}
                                    </span>
                                    @if ($file->is_current)
                                        <span class="badge-success">Current</span>
                                    @else
                                        <span class="badge-neutral">Superseded</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section class="card p-5">
                <h2 class="text-base font-semibold text-neutral-900">Impact</h2>
                <p class="mt-1 mb-3 text-sm text-neutral-600">Owners = students who bought this paper's bundle.</p>
                <dl class="grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-lg border border-neutral-200 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-neutral-500">Owners</dt>
                        <dd class="mt-1 text-sm font-bold text-neutral-900">{{ $paper->entitlements_count }}</dd>
                    </div>
                    <div class="rounded-lg border border-neutral-200 p-3">
                        <dt class="text-[11px] font-bold uppercase tracking-wide text-neutral-500">Bundle</dt>
                        <dd class="mt-1 text-sm font-bold text-neutral-900">{{ $paper->bundle?->status ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-neutral-500">
                    Type: {{ $paper->typeLabel() }} · Slug:
                    <code class="rounded bg-neutral-100 px-1 py-0.5 dark:!bg-neutral-800">{{ $paper->slug }}</code>
                </p>
            </section>
        </div>
    </div>

    {{-- ── Status + delete ────────────────────────────────────────── --}}
    <section class="card mt-6 p-5">
        <h2 class="text-base font-semibold text-neutral-900">Status</h2>
        <p class="mt-1 text-sm text-neutral-600">
            Publishing requires an attached PDF. Publishing a paper inside a bundle everyone already
            bought reaches those past buyers automatically (late uploads unlock for free).
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($paper->status === 'PUBLISHED')
                <form method="POST" action="{{ route('admin.papers.status', $paper) }}" class="inline">
                    @csrf
                    <input type="hidden" name="next" value="UNPUBLISHED">
                    <button class="btn-secondary btn-sm">Unpublish</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.papers.status', $paper) }}" class="inline">
                    @csrf
                    <input type="hidden" name="next" value="PUBLISHED">
                    <button class="btn-primary btn-sm" @disabled(! $currentFile)
                            title="{{ $currentFile ? 'Publish paper' : 'Attach a PDF first' }}">Publish</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.papers.destroy', $paper) }}" class="inline"
                  onsubmit="return confirm('Delete “{{ $paper->title }}”? This cannot be undone.')">
                @csrf @method('DELETE')
                <button class="btn-danger btn-sm" @disabled($paper->entitlements_count > 0)
                        title="{{ $paper->entitlements_count > 0 ? $paper->entitlements_count.' student(s) have paid access — cannot be deleted' : 'Permanently delete this paper' }}">
                    Delete
                </button>
            </form>
        </div>
    </section>
@endsection
