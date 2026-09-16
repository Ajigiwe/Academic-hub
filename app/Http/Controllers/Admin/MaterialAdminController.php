<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\PdfWatermarker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    public function store(Request $request, DocumentStore $store, PdfWatermarker $probe)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:6', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['LECTURE_NOTES', 'SLIDES', 'REVISION', 'PRACTICE'])],
            'level' => ['required', 'integer', 'min:100', 'max:400'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'course_code' => ['required', 'string', 'min:2', 'max:20'],
            'course_title' => ['required', 'string', 'min:3', 'max:120'],
            'programme' => ['required', 'string', 'exists:programmes,name'],
            'file' => ['required', 'file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf'],
        ]);

        $programme = Programme::where('name', $data['programme'])->firstOrFail();

        $uploaded = $request->file('file');
        $bytes = file_get_contents($uploaded->getRealPath());
        $pageCount = $probe->pageCount($bytes);
        if ($pageCount === 0) {
            return back()->with('error', 'The file could not be read as a PDF.');
        }

        $course = $this->upsertCourse($data['course_code'], $data['course_title']);
        $key = $store->buildStorageKey();
        $store->put($key, $bytes);

        DB::transaction(function () use ($data, $programme, $course, $key, $bytes, $pageCount, $uploaded) {
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
                'original_name' => $uploaded->getClientOriginalName(),
                'is_current' => true,
            ]);
        });

        return redirect()
            ->route('admin.materials')
            ->with('status', "\"{$data['title']}\" published as a free download.");
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
