<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Bundle, Programme, Resource, ResourceFile};
use App\Services\Documents\DocumentCompiler;
use App\Services\Documents\DocumentStore;
use App\Services\Documents\PdfWatermarker;
use App\Services\Payments\EntitlementBackfill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class PaperAdminController extends Controller
{
    private const MAX_UPLOAD_BYTES = 30 * 1024 * 1024; // 30 MB, mirrors the Next.js limit

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        return view('admin.papers', [
            'papers' => Resource::with(['course', 'bundle', 'files'])
                ->withCount('entitlements')
                ->when($q !== '', fn ($query) => $query->where('title', 'like', "%{$q}%"))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString(),
            'q' => $q,
            'status' => $status,
        ]);
    }

    /**
     * Paper detail/edit page — port of the Next.js admin/resources/[id]
     * page: catalog metadata form, document file card with version
     * history, and the impact stats (owners + bundle status).
     */
    public function show(Resource $resource)
    {
        $resource->load(['course', 'programme', 'bundle.course', 'files' => fn ($q) => $q->orderByDesc('created_at')])
            ->loadCount('entitlements');

        return view('admin.papers-show', [
            'paper' => $resource,
            'programmes' => Programme::orderBy('name')->pluck('name'),
            'currentFile' => $resource->files->first(fn ($f) => $f->is_current),
        ]);
    }

    /**
     * Update the editable catalog metadata (port of updateResourceAction).
     * Description, type, and programme are editable after creation;
     * course, level, semester, year, and bundle are fixed so existing
     * orders keep their meaning.
     */
    public function update(Request $request, Resource $resource)
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['PAST_QUESTION', 'LECTURE_NOTES', 'SLIDES', 'REVISION', 'PRACTICE'])],
            'programme_name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $name = trim($data['programme_name']);
        $slug = Programme::slugify($name);
        if ($slug === '') {
            return back()->with('error', 'Could not generate a URL slug from that programme name.');
        }

        $programme = Programme::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );

        $resource->update([
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'programme_id' => $programme->id,
        ]);

        return back()->with('status', 'Paper updated. Catalog pages reflect the change immediately.');
    }

    /** Port of setResourceStatusAction: no file → cannot publish. */
    public function setStatus(Request $request, Resource $resource, EntitlementBackfill $backfill)
    {
        $next = (string) $request->input('next');

        if ($next === 'PUBLISHED') {
            if (! $resource->files()->exists()) {
                return back()->with('error', "Cannot publish \"{$resource->title}\" — no PDF attached yet.");
            }
            $resource->update(['status' => 'PUBLISHED', 'published_at' => now()]);
            // Late uploads reach past buyers of the containing bundle.
            if ($resource->bundle_id) {
                $backfill->grantBundlePapersToPastBuyers($resource->bundle_id);
            }
        } elseif ($next === 'UNPUBLISHED' && $resource->status === 'PUBLISHED') {
            $resource->update(['status' => 'UNPUBLISHED']);
        } elseif ($next === 'DRAFT') {
            $resource->update(['status' => 'DRAFT', 'published_at' => null]);
        } elseif ($next === 'ARCHIVED') {
            $resource->update(['status' => 'ARCHIVED']);
        }

        return back()->with('status', "Paper status set to {$next}.");
    }

    /**
     * Port of deleteResourceAction: anything a paid order references is
     * protected — buyers keep access to what they paid for. The outcome
     * is never a silent no-op.
     */
    public function destroy(Resource $resource)
    {
        if ($resource->entitlements()->exists()) {
            return redirect()
                ->route('admin.papers')
                ->with('error', "Paper \"{$resource->title}\" is owned by {$resource->entitlements()->count()} student(s) and cannot be deleted.");
        }

        $title = $resource->title;
        $resource->delete(); // files cascade via FK

        return redirect()
            ->route('admin.papers')
            ->with('status', "Paper \"{$title}\" deleted.");
    }

    /** Upload one or more PDFs/images as a new DRAFT paper inside a bundle. */
    public function storeFile(Request $request, DocumentStore $store, DocumentCompiler $compiler)
    {
        $uploaded = $request->file('files');
        if (! $uploaded) {
            $single = $request->file('file');
            $uploaded = $single ? (is_array($single) ? $single : [$single]) : [];
        }

        $request->merge(['_has_files' => ! empty($uploaded)]);

        $data = $request->validate([
            'bundle_id' => ['required', 'integer', 'exists:bundles,id'],
            'title' => ['required', 'string', 'max:255'],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            '_has_files' => ['accepted'],
            'file' => ['nullable', 'file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
            'files' => ['nullable', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            '_has_files.accepted' => 'Please select at least one document or image file to upload.',
            'file.mimes' => 'The uploaded file must be a PDF or image (JPG, PNG, WebP).',
            'files.*.mimes' => 'Each uploaded file must be a PDF or image (JPG, PNG, WebP).',
        ]);

        $bundle = Bundle::findOrFail($data['bundle_id']);

        try {
            $compiled = $compiler->compile($uploaded);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not process upload: '.$e->getMessage());
        }

        $bytes = $compiled['bytes'];
        $pageCount = $compiled['page_count'];
        $originalName = $compiled['original_name'];

        $key = $store->buildStorageKey();
        $store->put($key, $bytes);

        DB::transaction(function () use ($bundle, $data, $key, $bytes, $pageCount, $originalName) {
            $resource = Resource::create([
                'slug' => $this->uniqueSlug($data['title']),
                'title' => $data['title'],
                'description' => $bundle->description,
                'type' => 'PAST_QUESTION',
                'status' => 'DRAFT',
                'level' => $bundle->level,
                'semester' => $data['semester'],
                'academic_year' => $bundle->academic_year,
                'page_count' => $pageCount,
                'preview_pages' => min(2, $pageCount),
                'bundle_id' => $bundle->id,
                'programme_id' => $bundle->programme_id,
                'course_id' => $bundle->course_id,
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
            ->route('admin.papers')
            ->with('status', "Paper \"{$data['title']}\" uploaded as a draft ({$pageCount} page".($pageCount === 1 ? '' : 's').'). Publish it when ready.');
    }

    /** Replace the current file of an existing paper with a PDF or image(s). */
    public function replaceFile(Request $request, Resource $resource, DocumentStore $store, DocumentCompiler $compiler)
    {
        $uploaded = $request->file('files');
        if (! $uploaded) {
            $single = $request->file('file');
            $uploaded = $single ? (is_array($single) ? $single : [$single]) : [];
        }

        $request->merge(['_has_files' => ! empty($uploaded)]);

        $request->validate([
            '_has_files' => ['accepted'],
            'file' => ['nullable', 'file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
            'files' => ['nullable', 'array', 'min:1'],
            'files.*' => ['file', 'max:'.(self::MAX_UPLOAD_BYTES / 1024), 'mimes:pdf,jpg,jpeg,png,webp'],
        ], [
            '_has_files.accepted' => 'Please select at least one document or image file to upload.',
            'file.mimes' => 'The uploaded file must be a PDF or image (JPG, PNG, WebP).',
            'files.*.mimes' => 'Each uploaded file must be a PDF or image (JPG, PNG, WebP).',
        ]);

        try {
            $compiled = $compiler->compile($uploaded);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not process upload: '.$e->getMessage());
        }

        $bytes = $compiled['bytes'];
        $pageCount = $compiled['page_count'];
        $originalName = $compiled['original_name'];

        $key = $store->buildStorageKey();
        $store->put($key, $bytes);

        DB::transaction(function () use ($resource, $key, $bytes, $pageCount, $originalName) {
            $resource->files()->update(['is_current' => false]);
            $resource->files()->create([
                'storage_key' => $key,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'original_name' => $originalName,
                'is_current' => true,
            ]);
            $resource->update(['page_count' => $pageCount]);
        });

        return back()->with('status', "Document replaced ({$pageCount} page".($pageCount === 1 ? '' : 's').').');
    }

    private function uniqueSlug(string $title): string
    {
        $base = \Illuminate\Support\Str::slug($title) ?: 'paper';
        $slug = $base;
        $i = 2;
        while (Resource::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
