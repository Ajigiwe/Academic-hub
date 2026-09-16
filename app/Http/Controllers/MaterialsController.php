<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\ContentCounts;
use App\Services\Documents\DocumentStore;
use App\Services\ProgrammeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Free materials: published resources that belong to NO sale bundle
 * (bundle_id null). Served to everyone — including guests — via the
 * public download route, mirroring the Next.js /materials page and
 * /api/resources/[slug]/download. Paid papers NEVER come through here;
 * they stay behind the tokenised viewer.
 */
class MaterialsController extends Controller
{
    public function index(Request $request)
    {
        $enabled = ProgrammeSettings::enabledSlugs();

        // A choice is only accepted when the programme is admin-enabled.
        $programme = in_array($request->query('programme'), $enabled, true)
            ? (string) $request->query('programme')
            : null;
        $level = in_array((int) $request->query('level'), [100, 200, 300, 400], true)
            ? (int) $request->query('level')
            : null;
        $semester = in_array($request->query('semester'), ['1', '2'], true)
            ? (int) $request->query('semester')
            : null;

        $guided = $programme !== null && $level !== null && $semester !== null;

        $materials = $guided
            ? $this->materialQuery($programme, $level, $semester, $enabled)->get()
            : collect();

        // Per-programme content counts for the step-1 programme cards:
        // published bundles for sale + published free materials, grouped
        // by slug — port of getProgrammeContentCounts.
        $programmes = Programme::whereIn('slug', $enabled)
            ->withCount([
                'bundles as published_bundles_count' => fn ($q) => $q->published(),
                'resources as published_materials_count' => fn ($q) => $q
                    ->where('status', 'PUBLISHED')->whereNull('bundle_id'),
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($p) => [$p->slug => [
                'slug' => $p->slug,
                'name' => $p->name,
                'bundles' => $p->published_bundles_count,
                'materials' => $p->published_materials_count,
            ]]);

        // Per-card counts for steps 2 and 3. This page serves the free
        // library, so only materials are counted — a bundle count here
        // would advertise content that cannot be downloaded from it.
        $stepCounts = [];
        if ($programme !== null) {
            $programmeId = Programme::where('slug', $programme)->value('id');
            $stepCounts['level'] = ContentCounts::levelCards($programmeId, withBundles: false);
            if ($level !== null) {
                $stepCounts['semester'] = ContentCounts::semesterCards($programmeId, $level, withBundles: false);
            }
        }

        return view('materials', [
            'programmes' => $programmes,
            'programme' => $programme,
            'level' => $level,
            'semester' => $semester,
            'guided' => $guided,
            'chips' => collect([
                $programme ? Programme::shortName($programme) : null,
                $level ? "Level {$level}" : null,
                $semester === 1 ? 'First Semester' : ($semester === 2 ? 'Second Semester' : null),
            ])->filter()->values(),
            'materials' => $materials,
            'stepCounts' => $stepCounts,
        ]);
    }

    /**
     * Public download route for FREE materials only. A resource is
     * downloadable when it has no bundle, is PUBLISHED, and its
     * programme track is enabled. Everything else is a bare 404.
     */
    public function download(string $slug, DocumentStore $store)
    {
        $resource = Resource::published()
            ->whereNull('bundle_id')
            ->where('slug', $slug)
            ->with(['course', 'programme', 'files' => fn ($q) => $q
                ->where('is_current', true)->latest()->limit(1)])
            ->first();

        if (! $resource || $resource->files->isEmpty()) {
            abort(404);
        }
        // Disabled track → the material does not exist for the visitor.
        if (! ProgrammeSettings::isEnabled($resource->programme->slug)) {
            abort(404);
        }

        $bytes = $store->get($resource->files->first()->storage_key);

        // Safe download filename: course code + slug, never the original name.
        $code = str_replace(' ', '-', (string) ($resource->course->code ?? 'material'));
        $filename = $code.'-'.Str::slug($resource->title).'.pdf';

        // Best-effort counter — never blocks the response.
        Resource::withoutTimestamps(
            fn () => $resource->increment('download_count'),
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function materialQuery(string $programme, int $level, int $semester, array $enabled)
    {
        return Resource::published()
            ->whereNull('bundle_id')
            ->whereIn('programme_id', Programme::whereIn('slug', $enabled)->select('id'))
            ->where('programme_id', Programme::where('slug', $programme)->value('id'))
            ->where('level', $level)
            ->where('semester', $semester)
            ->with(['course', 'programme', 'files' => fn ($q) => $q
                ->where('is_current', true)])
            // Port of the getFreeMaterials ordering. Explicit select so
            // the joined courses.title can't clobber resources.title.
            ->select('resources.*')
            ->leftJoin('courses', 'courses.id', '=', 'resources.course_id')
            ->orderBy('courses.code')
            ->orderBy('resources.semester')
            ->orderBy('resources.title');
    }
}
