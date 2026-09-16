<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Programme;
use App\Models\Resource;
use App\Services\ProgrammeSettings;
use Illuminate\Http\Request;

/**
 * Student search — port of the Next.js /search page and its
 * searchBundles/getFreeMaterials helpers. One page over both paid
 * bundles and free materials, sharing the same filters (except the
 * academic year, which only applies to bundles: materials are tagged
 * with the current year, so a year filter would hide them).
 *
 * Every listing respects Admin → Settings programme visibility.
 */
class SearchController extends Controller
{
    /** Port of SEARCH_PER_PAGE. */
    private const PER_PAGE = 12;

    private const SORTS = ['relevance', 'newest', 'popular', 'price_asc', 'price_desc'];

    public function index(Request $request)
    {
        $enabled = ProgrammeSettings::enabledSlugs();

        $q = trim((string) $request->query('q', ''));
        // An unknown/disabled programme filter simply doesn't apply.
        $programme = in_array($request->query('programme'), $enabled, true)
            ? (string) $request->query('programme')
            : null;
        $level = in_array((int) $request->query('level'), Programme::LEVELS, true)
            ? (int) $request->query('level')
            : null;
        $semester = in_array($request->query('semester'), ['1', '2'], true)
            ? (int) $request->query('semester')
            : null;
        $year = trim((string) $request->query('year', ''));
        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? (string) $request->query('sort')
            : 'relevance';

        // ── Bundles (port of searchBundles) ─────────────────────────
        $bundles = Bundle::published()
            // Visibility: disabled tracks are hidden from search entirely.
            ->whereIn('programme_id', Programme::whereIn('slug', $enabled)->select('id'))
            ->when($programme !== null, fn ($query) => $query
                ->where('programme_id', Programme::where('slug', $programme)->value('id')))
            ->when($level !== null, fn ($query) => $query->where('level', $level))
            ->when($year !== '', fn ($query) => $query->where('academic_year', $year))
            // A bundle belongs to a semester when at least one of its
            // published papers does.
            ->when($semester !== null, fn ($query) => $query
                ->whereHas('resources', fn ($r) => $r->published()->where('semester', $semester)))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(fn ($w) => $w
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('academic_year', 'like', $like)
                    ->orWhereHas('course', fn ($c) => $c
                        ->where('code', 'like', $like)
                        ->orWhere('title', 'like', $like))
                    ->orWhereHas('programme', fn ($p) => $p->where('name', 'like', $like)));
            })
            ->with(['course', 'programme', 'resources' => fn ($r) => $r->published()])
            ->withCount(['orderItems as order_items_count'])
            ->when($sort === 'newest' || $sort === 'relevance', fn ($query) => $query->orderByDesc('published_at'))
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy('price_pesewas'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('price_pesewas'))
            ->when($sort === 'popular', fn ($query) => $query
                ->orderByDesc('order_items_count')
                ->orderByDesc('published_at'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // ── Free materials (port of getFreeMaterials) ───────────────
        $freeMaterials = Resource::published()
            ->select('resources.*')
            ->whereNull('bundle_id')
            ->whereIn('programme_id', Programme::whereIn('slug', $enabled)->select('id'))
            ->when($programme !== null, fn ($query) => $query
                ->where('programme_id', Programme::where('slug', $programme)->value('id')))
            ->when($level !== null, fn ($query) => $query->where('level', $level))
            ->when($semester !== null, fn ($query) => $query->where('semester', $semester))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(fn ($w) => $w
                    ->where('resources.title', 'like', $like)
                    ->orWhere('resources.description', 'like', $like)
                    ->orWhereHas('course', fn ($c) => $c
                        ->where('courses.code', 'like', $like)
                        ->orWhere('courses.title', 'like', $like))
                    ->orWhereHas('programme', fn ($p) => $p->where('programmes.name', 'like', $like)));
            })
            // Ordering is course-code → semester → title in the original.
            ->leftJoin('courses', 'courses.id', '=', 'resources.course_id')
            ->orderBy('courses.code')
            ->orderBy('resources.semester')
            ->orderBy('resources.title')
            ->with(['course', 'programme', 'files' => fn ($f) => $f->where('is_current', true)])
            ->get();

        // ── Filter dropdown years (port of getFilterYears) ──────────
        $years = Bundle::published()
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        $activeFilterCount = collect([$q !== '' ? $q : null, $programme, $level, $semester, $year !== '' ? $year : null])
            ->filter()->count();

        return view('search', [
            'q' => $q,
            'programme' => $programme,
            'level' => $level,
            'semester' => $semester,
            'year' => $year,
            'sort' => $sort,
            'years' => $years,
            'bundles' => $bundles,
            'freeMaterials' => $freeMaterials,
            'activeFilterCount' => $activeFilterCount,
        ]);
    }
}
