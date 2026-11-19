<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\Documents\DocumentCompiler;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\PdfWatermarker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin free-material uploads — port of uploadFreeMaterial
 * (src/lib/resource-admin.ts): ONE published, bundle-less resource
 * served via the public download route. Created PUBLISHED immediately
 * (free content needs no review cycle).
 */
class MaterialAdminController extends Controller
{
    private const MAX_UPLOAD_BYTES = 30 * 1024 * 1024; // 30 MB, mirrors Next.js

    public function index()
    {
        return view('admin.materials', [
            'materials' => Resource::whereNull('bundle_id')
                ->with(['course', 'programme', 'files'])
                ->orderByDesc('created_at')
                ->paginate(20),
            'programmes' => Programme::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, DocumentStore $store, DocumentCompiler $compiler)
    {
        $uploaded = $request->file('files');
        if (! $uploaded) {
            $single = $request->file('file');
            $uploaded = $single ? (is_array($single) ? $single : [$single]) : [];
        }

        $request->merge(['_has_files' => ! empty($uploaded)]);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:6', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['PAST_QUESTION', 'LECTURE_NOTES', 'SLIDES', 'REVISION', 'PRACTICE'])],
            'level' => ['required', 'integer', 'min:100', 'max:400'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'course_code' => ['required', 'string', 'min:2', 'max:20'],
            'course_title' => ['required', 'string', 'min:3', 'max:120'],
            'programme' => ['required', 'string', 'exists:programmes,name'],
            '_has_files' => ['accepted'],
            'file' => ['nullable', 'file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
            'files' => ['nullable', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            '_has_files.accepted' => 'Please select at least one document or image file to upload.',
            'file.mimes' => 'The uploaded file must be a PDF or image (JPG, PNG, WebP).',
            'files.*.mimes' => 'Each uploaded file must be a PDF or image (JPG, PNG, WebP).',
        ]);

        $programme = Programme::where('name', $data['programme'])->firstOrFail();

        try {
            $compiled = $compiler->compile($uploaded);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not process upload: '.$e->getMessage());
        }

        $bytes = $compiled['bytes'];
        $pageCount = $compiled['page_count'];
        $originalName = $compiled['original_name'];

        $course = $this->upsertCourse($data['course_code'], $data['course_title']);
        $key = $store->buildStorageKey();
        $store->put($key, $bytes);

        DB::transaction(function () use ($data, $programme, $course, $key, $bytes, $pageCount, $originalName) {
            $resource = Resource::create([
                'slug' => $this->uniqueSlug($data['title']),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'status' => 'PUBLISHED', // free content goes live immediately
                'level' => $data['level'],
                'semester' => $data['semester'],
                'academic_year' => $this->currentAcademicYear(),
                'page_count' => $pageCount,
                'preview_pages' => 0,
                'published_at' => now(),
                'bundle_id' => null, // the defining trait of a free material
                'programme_id' => $programme->id,
                'course_id' => $course->id,
            ]);

            $resource->files()->create([
                'storage_key' => $key,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'original_name' => $originalName,
                'is_current' => true,
            ]);
        });

        return redirect()
            ->route('admin.materials')
            ->with('status', "\"{$data['title']}\" published as a free download ({$pageCount} page".($pageCount === 1 ? '' : 's').').');
    }

    /** Course by code — created on first use, title kept in sync. */
    private function upsertCourse(string $code, string $title): Course
    {
        return Course::updateOrCreate(
            ['code' => $code],
            ['title' => $title, 'slug' => $this->uniqueCourseSlug($code, $title)],
        );
    }

    private function uniqueCourseSlug(string $code, string $title): string
    {
        $base = Str::slug($code.'-'.$title) ?: 'course';
        $slug = $base;
        $i = 2;
        while (Course::where('slug', $slug)->where('code', '!=', $code)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function currentAcademicYear(): string
    {
        $month = (int) now()->format('n');
        $year = (int) now()->format('Y');
        $start = $month >= 8 ? $year : $year - 1;

        return $start.'/'.($start + 1);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'material';
        $slug = $base;
        $i = 2;
        while (Resource::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
