<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\ContentCounts;
use App\Services\ProgrammeSettings;
use Illuminate\Http\Request;

/**
 * Public catalog. Every listing respects Admin → Settings programme
 * visibility: a disabled track is invisible everywhere and its bundle
 * pages 404 — mirroring the Next.js settings gateways.
 */
class CatalogController extends Controller
{
    public function home()
    {
        // The homepage is the original's hero + two intent doors — no
        // catalog queries needed. (Courses/materials pages carry the
        // programme-visibility filtering.)
        return view('home');
    }

    public function courses(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $enabled = ProgrammeSettings::enabledSlugs();

        // Guided browse flow — a choice is only accepted when the
        // programme is admin-enabled (same gate as the Next.js page).
        $programme = in_array($request->query('programme'), $enabled, true)
            ? (string) $request->query('programme')
            : null;
        $level = in_array((int) $request->query('level'), Programme::LEVELS, true)
            ? (int) $request->query('level')
            : null;
        $semester = in_array($request->query('semester'), ['1', '2'], true)
            ? (int) $request->query('semester')
            : null;

        // The library only renders once ALL THREE steps are complete.
        $guided = $programme !== null && $level !== null && $semester !== null;

        $bundles = collect();
        $freeMaterials = collect();
        if ($guided) {
            // A bundle belongs to a semester when at least one of its
            // published papers does (port of searchBundles semantics).
            $bundles = Bundle::published()
                ->whereIn('programme_id', Programme::whereIn('slug', $enabled)->select('id'))
                ->where('programme_id', Programme::where('slug', $programme)->value('id'))
                ->where('level', $level)
                ->whereHas('resources', fn ($r) => $r->published()->where('semester', $semester))
                ->with(['course', 'programme', 'resources' => fn ($r) => $r->published()])
                ->withCount('resources')
                ->orderBy('title')
                ->get();

            // Free materials matching the same three filters. Explicit
            // select so the joined courses.title can't clobber the model.
            $freeMaterials = Resource::published()
                ->select('resources.*')
                ->whereNull('bundle_id')
                ->whereIn('programme_id', Programme::whereIn('slug', $enabled)->select('id'))
                ->where('programme_id', Programme::where('slug', $programme)->value('id'))
                ->where('level', $level)
                ->where('semester', $semester)
                ->with(['course', 'programme', 'files' => fn ($f) => $f->where('is_current', true)])
                ->leftJoin('courses', 'courses.id', '=', 'resources.course_id')
                ->orderBy('courses.code')
                ->orderBy('resources.semester')
                ->orderBy('resources.title')
                ->get();
        }

        $programmes = Programme::whereIn('slug', $enabled)
            ->withCount([
                'bundles as published_bundles_count' => fn ($b) => $b->published(),
                'resources as published_materials_count' => fn ($r) => $r
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

        // Per-card content counts for steps 2 and 3, so a level or
        // semester card says what is behind it ("2 bundles · 1 free
        // material") before the visitor commits to the click. Levels show
        // both kinds; semesters only appear once a level is chosen.
        $stepCounts = [];
        if ($programme !== null) {
            $programmeId = Programme::where('slug', $programme)->value('id');
            $stepCounts['level'] = ContentCounts::levelCards($programmeId);
            if ($level !== null) {
                $stepCounts['semester'] = ContentCounts::semesterCards($programmeId, $level);
            }
        }

        return view('courses', [
            'q' => $q,
            'programmes' => $programmes,
            'programme' => $programme,
            'level' => $level,
            'semester' => $semester,
            'guided' => $guided,
            'anyFilter' => $programme !== null || $level !== null || $semester !== null,
            'chips' => collect([
                $programme ? \App\Models\Programme::shortName($programme) : null,
                $level ? "Level {$level}" : null,
                $semester === 1 ? 'First Semester' : ($semester === 2 ? 'Second Semester' : null),
            ])->filter()->values(),
            'bundles' => $bundles,
            'freeMaterials' => $freeMaterials,
            'stepCounts' => $stepCounts,
        ]);
    }

    public function showBundle(string $slug)
    {
        $bundle = Bundle::published()
            ->with(['course', 'programme'])
            ->where('slug', $slug)
            ->firstOrFail();

        $papers = $bundle->resources()->published()->orderBy('semester')->orderBy('title')->get();

        // A student owns the bundle when entitled to every published paper
        // inside it (same rule as the Next.js detail page). Individual
        // entitlements are tracked too, so a partial owner can still open
        // the papers they already paid for.
        $owned = false;
        $ownedResourceIds = collect();
        $user = request()->user();
        if ($user && $papers->isNotEmpty()) {
            $ownedResourceIds = \App\Models\Entitlement::where('user_id', $user->id)
                ->whereIn('resource_id', $papers->pluck('id'))
                ->where('status', 'active')
                ->pluck('resource_id');
            $owned = $ownedResourceIds->count() === $papers->count();
        }

        // Hidden programme: nobody reaches the page by guessing the URL —
        // except students who already own it (their purchase is never
        // revoked). Port of the Next.js isProgrammeEnabled escape hatch.
        if (! ProgrammeSettings::isEnabled($bundle->programme->slug) && ! $owned) {
            abort(404);
        }

        return view('bundles-show', [
            'bundle' => $bundle,
            'papers' => $papers,
            'owned' => $owned,
            'ownedResourceIds' => $ownedResourceIds,
        ]);
    }
}
