<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Course;
use App\Models\Programme;
use App\Services\BundlePricing;
use App\Services\Payments\EntitlementBackfill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BundleAdminController extends Controller
{
    public function index()
    {
        return view('admin.bundles', [
            'bundles' => Bundle::with(['course', 'programme'])
                ->withCount(['resources', 'orderItems'])
                ->withExists(['resources' => fn ($q) => $q->where('status', '!=', 'PUBLISHED')->whereNull('published_at')])
                ->orderByDesc('created_at')
                ->paginate(20),
            'programmes' => Programme::orderBy('name')->get(),
            'courses' => Course::orderBy('code')->get(),
        ]);
    }

    /**
     * Create a new past question bundle.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'programme_name' => ['nullable', 'string', 'max:120'],
            'course_code' => ['required', 'string', 'max:30'],
            'course_title' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'in:100,200,300,400'],
            'academic_year' => ['required', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        // Resolve or create Programme
        $programme = null;
        if (!empty($data['programme_id'])) {
            $programme = Programme::find($data['programme_id']);
        }
        if (!$programme && !empty($data['programme_name'])) {
            $name = trim($data['programme_name']);
            $programme = Programme::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            );
        }
        if (!$programme) {
            $programme = Programme::first() ?? Programme::create([
                'name' => 'General Studies',
                'slug' => 'general-studies',
            ]);
        }

        // Resolve or create Course
        $code = strtoupper(trim($data['course_code']));
        $courseTitle = trim($data['course_title']);
        $course = Course::firstOrCreate(
            ['code' => $code],
            [
                'title' => $courseTitle,
                'slug' => Str::slug("{$code} {$courseTitle}"),
            ]
        );

        $academicYear = trim($data['academic_year']);
        $level = (int) $data['level'];

        $bundleTitle = !empty($data['title'])
            ? trim($data['title'])
            : "{$course->code} — {$course->title} · {$academicYear} Past Questions";

        $baseSlug = Str::slug("{$course->code}-{$academicYear}");
        $slug = $baseSlug;
        $counter = 1;
        while (Bundle::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $bundle = Bundle::create([
            'slug' => $slug,
            'title' => $bundleTitle,
            'description' => !empty($data['description'])
                ? trim($data['description'])
                : "Past questions and study papers for {$course->code} ({$academicYear}).",
            'level' => $level,
            'academic_year' => $academicYear,
            'price_pesewas' => BundlePricing::pesewas(),
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'status' => 'DRAFT',
        ]);

        return back()->with('status', "Bundle \"{$bundle->title}\" created. You can now attach papers to it.");
    }

    /**
     * Populate starter courses and bundles from PastqSeeder.
     */
    public function seed()
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\PastqSeeder',
            '--force' => true,
        ]);

        return back()->with('status', 'Starter courses and bundles have been loaded successfully into the catalogue.');
    }

    /**
     * Publish / unpublish. Port of setBundleStatusAction:
     * cannot publish an empty bundle (buying it would grant nothing);
     * publishing takes all papers live and backfills past buyers.
     */
    public function setStatus(Request $request, Bundle $bundle, EntitlementBackfill $backfill)
    {
        $next = (string) $request->input('next');

        if ($next === 'PUBLISHED' && $bundle->resources()->count() > 0) {
            $bundle->update(['status' => 'PUBLISHED', 'published_at' => now()]);
            // The bulk model: publishing the bundle takes all its papers live.
            $bundle->resources()->where(function ($q) {
                $q->where('status', '!=', 'PUBLISHED')->orWhereNull('published_at');
            })->update(['status' => 'PUBLISHED', 'published_at' => now()]);
            // Late uploads reach everyone who already bought this bundle.
            $backfill->grantBundlePapersToPastBuyers($bundle->id);
        } elseif ($next === 'UNPUBLISHED' && $bundle->status === 'PUBLISHED') {
            $bundle->update(['status' => 'UNPUBLISHED']);
        }

        return back();
    }

    /**
     * Port of deleteBundleAction: bundles that appear on any order are
     * permanent purchase records and cannot be deleted — unpublish or
     * archive instead. Ends with a redirect carrying a notice so the
     * outcome is never a silent no-op.
     */
    public function destroy(Bundle $bundle)
    {
        if ($bundle->orderItems()->exists()) {
            return redirect()
                ->route('admin.bundles')
                ->with('error', "Bundle \"{$bundle->title}\" appears on paid orders and cannot be deleted — unpublish or archive it instead.");
        }

        $title = $bundle->title;
        $bundle->delete(); // papers cascade via FK; files cascade with them

        return redirect()
            ->route('admin.bundles')
            ->with('status', "Bundle \"{$title}\" deleted.");
    }
}
