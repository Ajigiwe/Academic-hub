@extends('admin.layout')

@section('title', 'Admin — Papers')

@section('admin-content')
@php use App\Support\Format; @endphp
    <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Papers</h1>
    <p class="mt-1 max-w-3xl text-sm text-neutral-600 dark:text-neutral-400">
        Papers live inside bundles — students buy one bundle per course and academic year, so upload PDFs
        in bulk. Papers stay drafts until the bundle is published.
    </p>

    {{-- ── Upload ─────────────────────────────────────────────────── --}}
    <section class="card mt-5 overflow-hidden">
        <div class="border-b border-neutral-100 bg-neutral-50 px-5 py-4 dark:border-neutral-800 dark:bg-neutral-900">
            <h2 class="text-base font-semibold text-neutral-900 dark:text-white">Upload PDFs in bulk</h2>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                Each file is sniffed and probed server-side (magic bytes, page count, encryption) before
                anything is stored. Uploads land as drafts — publish is a separate decision.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.papers.store') }}" enctype="multipart/form-data"
              class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            @csrf
            <label>
                <span class="label">Bundle</span>
                <select name="bundle_id" required class="input text-sm">
                    @foreach (\App\Models\Bundle::with('course')->orderBy('title')->get() as $b)
                        <option value="{{ $b->id }}">{{ $b->course->code }} · {{ $b->academic_year }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Title</span>
                <input type="text" name="title" required placeholder="MKT 203 · Semester 2 Exam" class="input text-sm">
            </label>
            <label>
                <span class="label">Semester</span>
                <select name="semester" class="input text-sm">
                    <option value="1">1</option>
                    <option value="2">2</option>
                </select>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Document (PDF or image files · multiple supported)</span>
                <input type="file" name="files[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp" required class="w-full text-xs" data-file-preview>
                <span class="mt-1 block text-xs text-neutral-500">
                    Upload a PDF or select multiple image files (JPG, PNG, WebP) to compile them into a clear multi-page document in order. Max 30 MB per file.
                </span>
                <div class="file-preview-zone mt-2.5 hidden rounded-lg border border-neutral-200 bg-neutral-50 p-2.5 dark:border-neutral-800 dark:bg-neutral-900/60">
                    <p class="file-preview-summary text-xs font-semibold text-neutral-700 dark:text-neutral-300"></p>
                    <div class="file-preview-items mt-2 flex flex-wrap gap-2"></div>
                </div>
            </label>
            <div class="sm:col-span-2">
                <button class="btn-primary">Upload draft</button>
            </div>
        </form>
    </section>

    {{-- ── Filters ────────────────────────────────────────────────── --}}
    <form method="GET" class="mt-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-neutral-900 dark:text-white">
                Catalog
                <span class="ml-1 font-normal text-neutral-500 dark:text-neutral-400">({{ number_format($papers->total()) }})</span>
            </h2>
            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                @if ($q !== '' || $status !== '')
                    Filtered — showing {{ number_format($papers->total()) }} {{ Str::plural('match', $papers->total()) }}.
                    <a href="{{ route('admin.papers') }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">Clear</a>
                @else
                    Newest first · 20 per page
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ $q }}" placeholder="Search title…" class="input w-44 !py-2 text-sm">
            <select name="status" class="input w-36 !py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['DRAFT', 'PUBLISHED', 'UNPUBLISHED', 'ARCHIVED'] as $s)
                    <option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>
                @endforeach
            </select>
            <button class="btn-secondary btn-sm">Filter</button>
        </div>
    </form>

    {{-- ── Catalog cards ──────────────────────────────────────────── --}}
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2 2xl:grid-cols-3">
        @forelse ($papers as $paper)
            @php $current = $paper->files->firstWhere('is_current', true); @endphp
            <article class="card flex flex-col overflow-hidden">
                {{-- Identity --}}
                <div class="flex items-start justify-between gap-3 border-b border-neutral-100 px-4 py-3.5 sm:px-5 dark:border-neutral-800">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="badge-brand font-semibold">{{ $paper->course?->code ?? '—' }}</span>
                            <span class="badge-neutral">Level {{ $paper->level }}</span>
                            <span class="badge-neutral">Sem {{ $paper->semester }}</span>
                            <span class="badge-neutral">{{ $paper->academic_year }}</span>
                            @unless ($paper->bundle)
                                <span class="badge-gold font-semibold">Free download</span>
                            @endunless
                        </div>
                        <h3 class="mt-2 text-[15px] font-bold leading-snug text-neutral-900 dark:text-white">
                            <a href="{{ route('admin.papers.show', $paper) }}"
                               class="transition-colors hover:text-brand-800 hover:underline dark:hover:text-brand-300">{{ $paper->title }}</a>
                        </h3>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                            @if ($paper->bundle)
                                In bundle {{ $paper->bundle->course?->code }} · {{ $paper->bundle->academic_year }}
                            @else
                                Not in a bundle — free for everyone
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 {{ match ($paper->status) {
                        'PUBLISHED' => 'badge-success',
                        'UNPUBLISHED' => 'badge-gold',
                        'ARCHIVED' => 'badge-danger',
                        default => 'badge-neutral',
                    } }}">{{ $paper->status }}</span>
                </div>

                {{-- Numbers --}}
                <dl class="grid grid-cols-3 divide-x divide-neutral-100 border-b border-neutral-100 text-center dark:divide-neutral-800 dark:border-neutral-800">
                    <div class="px-2 py-2.5">
                        @if ($current)
                            <dd class="text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{{ Format::bytes($current->size_bytes) }}</dd>
                            <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">PDF · {{ $current->created_at->format('d/m/Y') }}</dt>
                        @else
                            <dd class="text-sm font-bold text-amber-600 dark:text-amber-300">No file</dd>
                            <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Cannot publish</dt>
                        @endif
                    </div>
                    <div class="px-2 py-2.5">
                        <dd class="text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{{ $paper->page_count ?: '—' }}</dd>
                        <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ Str::plural('page', (int) $paper->page_count) }}</dt>
                    </div>
                    <div class="px-2 py-2.5">
                        <dd class="text-sm font-bold tabular-nums text-neutral-900 dark:text-white">{{ $paper->entitlements_count }}</dd>
                        <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ Str::plural('owner', $paper->entitlements_count) }}</dt>
                    </div>
                </dl>

                {{-- Actions --}}
                <div class="mt-auto px-4 pb-4 pt-3 sm:px-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.papers.show', $paper) }}" class="btn-secondary btn-sm">Edit</a>
                        @if ($paper->status !== 'PUBLISHED')
                            <form method="POST" action="{{ route('admin.papers.status', $paper) }}">
                                @csrf
                                <input type="hidden" name="next" value="PUBLISHED">
                                <button class="btn-primary btn-sm" @disabled(is_null($current))
                                        title="{{ $current ? 'Publish paper' : 'Attach a PDF before publishing' }}">Publish</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.papers.status', $paper) }}">
                                @csrf
                                <input type="hidden" name="next" value="UNPUBLISHED">
                                <button class="btn-secondary btn-sm">Unpublish</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.papers.destroy', $paper) }}" class="sm:ml-auto"
                              onsubmit="return confirm('Delete “{{ $paper->title }}”? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm" @disabled($paper->entitlements_count > 0)
                                    title="{{ $paper->entitlements_count > 0 ? $paper->entitlements_count.' student(s) have paid access — cannot be deleted' : 'Permanently delete “'.$paper->title.'”' }}">
                                Delete
                            </button>
                        </form>
                    </div>

                    @if ($current)
                        <details class="mt-2.5">
                            <summary class="cursor-pointer text-xs font-medium text-neutral-500 hover:text-brand-700 dark:text-neutral-400 dark:hover:text-brand-300">
                                Replace PDF / images…
                            </summary>
                            <form method="POST" action="{{ route('admin.papers.replace', $paper) }}"
                                  enctype="multipart/form-data" class="mt-2 flex flex-col gap-2">
                                @csrf
                                <input type="file" name="files[]" multiple accept="application/pdf,image/jpeg,image/png,image/webp" required class="text-xs" data-file-preview>
                                <div class="file-preview-zone hidden rounded-lg border border-neutral-200 bg-neutral-50 p-2 dark:border-neutral-800 dark:bg-neutral-900/60">
                                    <p class="file-preview-summary text-xs font-semibold text-neutral-700 dark:text-neutral-300"></p>
                                    <div class="file-preview-items mt-1.5 flex flex-wrap gap-2"></div>
                                </div>
                                <button class="btn-secondary btn-sm self-start">Upload version</button>
                            </form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <div class="card px-6 py-14 text-center xl:col-span-2 2xl:col-span-3">
                <p class="font-semibold text-neutral-800 dark:text-neutral-100">
                    {{ $q !== '' || $status !== '' ? 'No papers match these filters' : 'No papers yet' }}
                </p>
                <p class="mx-auto mt-1 max-w-md text-sm text-neutral-600 dark:text-neutral-400">
                    {{ $q !== '' || $status !== ''
                        ? 'Try a shorter search term or a different status.'
                        : 'Upload your first batch above — each PDF lands as a draft you can publish later.' }}
                </p>
                @if ($q !== '' || $status !== '')
                    <a href="{{ route('admin.papers') }}" class="btn-secondary mt-4">Clear filters</a>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-5">{{ $papers->links() }}</div>

    @push('scripts')
        <script>
            document.addEventListener('change', function (e) {
                if (!e.target.matches('input[data-file-preview]')) return;
                var input = e.target;
                var container = input.closest('form, label, div').querySelector('.file-preview-zone');
                if (!container) return;
                var summary = container.querySelector('.file-preview-summary');
                var items = container.querySelector('.file-preview-items');
                items.innerHTML = '';
                var files = Array.from(input.files || []);
                if (files.length === 0) {
                    container.classList.add('hidden');
                    return;
                }
                container.classList.remove('hidden');
                var isMulti = files.length > 1;
                summary.textContent = files.length + ' file' + (files.length === 1 ? '' : 's') + ' selected' + (isMulti ? ' · compiled into a multi-page document in this order:' : ':');
                files.forEach(function (file, idx) {
                    var card = document.createElement('div');
                    card.className = 'flex items-center gap-2 rounded-md border border-neutral-200 bg-white p-1.5 shadow-sm dark:border-neutral-700 dark:bg-neutral-800';
                    var isImg = file.type.startsWith('image/');
                    var thumb = document.createElement('div');
                    thumb.className = 'flex h-12 w-12 shrink-0 items-center justify-center rounded bg-neutral-100 overflow-hidden text-[10px] font-bold text-neutral-500 dark:bg-neutral-700';
                    if (isImg) {
                        var img = document.createElement('img');
                        img.src = URL.createObjectURL(file);
                        img.className = 'h-full w-full object-cover';
                        thumb.appendChild(img);
                    } else {
                        thumb.textContent = 'PDF';
                    }
                    var meta = document.createElement('div');
                    meta.className = 'min-w-0 pr-1 text-left';
                    meta.innerHTML = '<span class="inline-block rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-bold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">Page ' + (idx + 1) + '</span>' +
                        '<p class="truncate text-xs font-medium text-neutral-800 dark:text-neutral-200 max-w-[130px]">' + file.name + '</p>' +
                        '<p class="text-[10px] text-neutral-400">' + (file.size ? (file.size / 1024).toFixed(0) + ' KB' : '') + '</p>';
                    card.appendChild(thumb);
                    card.appendChild(meta);
                    items.appendChild(card);
                });
            });
        </script>
    @endpush
@endsection
