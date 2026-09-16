@extends('admin.layout')

@section('title', 'Free materials — Admin')

@section('admin-content')
    <h1 class="text-xl font-bold text-neutral-900">Free materials</h1>
    <p class="mt-1 text-sm text-neutral-600">
        Slides, lecture notes, and revision packs. Students download these for free — no bundle, no
        payment. They go live immediately and are visible to guests.
    </p>

    {{-- ── Upload ─────────────────────────────────────────────────── --}}
    <section class="card mt-5 p-5">
        <h2 class="text-base font-semibold text-neutral-900">Upload free materials (not for sale)</h2>
        <p class="mt-1 mb-4 text-sm text-neutral-600">
            Each file is sniffed and probed server-side (magic bytes, page count, encryption) before
            anything is stored. Tagged with a course, programme track, level, and semester so they show up
            in the same browse flow as paid papers.
        </p>
        <form method="POST" action="{{ route('admin.materials.store') }}" enctype="multipart/form-data"
              class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            @csrf
            <label class="sm:col-span-2">
                <span class="label">Title</span>
                <input name="title" required minlength="6" maxlength="160" class="input text-sm">
            </label>
            <label>
                <span class="label">Type</span>
                <select name="type" class="input text-sm">
                    @foreach (['LECTURE_NOTES', 'SLIDES', 'REVISION', 'PRACTICE'] as $type)
                        <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Programme</span>
                <select name="programme" class="input text-sm">
                    @foreach ($programmes as $programme)
                        <option value="{{ $programme->name }}">{{ $programme->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Level</span>
                <select name="level" class="input text-sm">
                    @foreach ([100, 200, 300, 400] as $lvl)
                        <option value="{{ $lvl }}">Level {{ $lvl }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Semester</span>
                <select name="semester" class="input text-sm">
                    <option value="1">First semester</option>
                    <option value="2">Second semester</option>
                </select>
            </label>
            <label>
                <span class="label">Course code (e.g. ICT 201)</span>
                <input name="course_code" required minlength="2" maxlength="20" class="input text-sm">
            </label>
            <label>
                <span class="label">Course title</span>
                <input name="course_title" required minlength="3" maxlength="120" class="input text-sm">
            </label>
            <label class="sm:col-span-2 lg:col-span-3">
                <span class="label">Description (optional)</span>
                <input name="description" maxlength="2000" class="input text-sm">
            </label>
            <label>
                <span class="label">PDF (max 30 MB)</span>
                <input type="file" name="file" accept="application/pdf" required class="text-xs">
            </label>
            <div class="sm:col-span-2 lg:col-span-4">
                <button class="btn-primary">Publish free material</button>
            </div>
        </form>
    </section>

    {{-- ── Published materials ───────────────────────────────────── --}}
    <h2 class="mt-8 text-base font-semibold text-neutral-900">
        Published materials ({{ $materials->total() }})
    </h2>

    <div class="card mt-3 md:overflow-x-auto">
        <table class="table-base table-responsive">
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Course</th>
                    <th>Track</th>
                    <th>File</th>
                    <th>Downloads</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($materials as $material)
                    @php $file = $material->files->firstWhere('is_current', true); @endphp
                    <tr>
                        <td data-label="Material">
                            <div class="md:text-right">
                                <span class="font-medium text-brand-800">{{ $material->title }}</span>
                                <p class="text-xs text-neutral-500">
                                    {{ ucfirst(strtolower(str_replace('_', ' ', $material->type))) }} · Level {{ $material->level }} · Sem {{ $material->semester }}
                                </p>
                            </div>
                        </td>
                        <td data-label="Course" class="text-xs">
                            <span class="badge-brand">{{ $material->course->code }}</span>
                        </td>
                        <td data-label="Track" class="text-xs text-neutral-600">{{ $material->programme->name }}</td>
                        <td data-label="File" class="text-xs text-neutral-600">
                            @if ($file)
                                <span class="badge-success">PDF</span>
                                {{ \App\Support\Format::bytes($file->size_bytes) }} · {{ $material->page_count }} pages
                            @else
                                <span class="badge-danger">No file</span>
                            @endif
                        </td>
                        <td data-label="Downloads" class="tabular-nums">{{ $material->download_count }}</td>
                        <td data-label="">
                            @if ($file)
                                <a href="{{ route('materials.download', $material->slug) }}" class="btn-secondary btn-sm">Download</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-neutral-500">No free materials yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $materials->links() }}</div>
@endsection
