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
            'title' => ['required', 'string', 'max:255'],
            'programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'programme_name' => ['nullable', 'string', 'max:120'],
            'level' => ['required', 'integer', 'in:100,200,300,400'],
            'course_code' => ['nullable', 'string', 'max:30'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        $title = trim($data['title']);

        // 1. Resolve or create Programme
        $programme = null;
        if (!empty($data['programme_id'])) {
            $programme = Programme::find($data['programme_id']);
        }
        if (!$programme && !empty($data['programme_name'])) {
            $progName = trim($data['programme_name']);
            $programme = Programme::firstOrCreate(
                ['name' => $progName],
                ['slug' => Str::slug($progName)]
            );
        }
        if (!$programme) {
            $programme = Programme::first() ?? Programme::create([
                'name' => 'General Studies',
                'slug' => 'general-studies',
            ]);
        }

        // 2. Resolve or generate Course Code
        $code = !empty($data['course_code']) ? strtoupper(trim($data['course_code'])) : null;
        if (!$code) {
            // Auto-generate code from title acronym (e.g. "Anatomy and Physiology" -> "AP 100")
            $words = preg_split('/[\s\-_]+/', $title);
            $initials = '';
            foreach ($words as $w) {
                if (!empty($w) && !in_array(strtolower($w), ['and', 'of', 'the', 'in', 'to', 'for', 'a', 'an'])) {
                    $initials .= strtoupper($w[0]);
                }
            }
            if (strlen($initials) < 2) {
                $initials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $title), 0, 4));
            }
            if (empty($initials)) {
                $initials = 'CRS';
            }
            $code = $initials . ' ' . $data['level'];
        }

        // Ensure unique course code
        $baseCode = $code;
        $cIndex = 1;
        while (Course::where('code', $code)->where('title', '!=', $title)->exists()) {
            $code = $baseCode . '-' . $cIndex;
            $cIndex++;
        }

        $course = Course::firstOrCreate(
            ['title' => $title],
            [
                'code' => $code,
                'slug' => Str::slug($title . '-' . Str::random(4)),
            ]
        );

        // 3. Academic Year (defaults cleanly to current academic year)
        $academicYear = !empty($data['academic_year'])
            ? trim($data['academic_year'])
            : (date('Y') . '/' . (date('Y') + 1));

        $level = (int) $data['level'];

        // 4. Unique bundle slug
        $baseSlug = Str::slug($title);
        $slug = $baseSlug ?: 'bundle-' . Str::random(6);
        $counter = 1;
        while (Bundle::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $bundle = Bundle::create([
            'slug' => $slug,
            'title' => $title,
            'description' => !empty($data['description'])
                ? trim($data['description'])
                : "Past questions and study papers for {$title}.",
            'level' => $level,
            'academic_year' => $academicYear,
            'price_pesewas' => BundlePricing::pesewas(),
            'programme_id' => $programme->id,
            'course_id' => $course->id,
            'status' => 'DRAFT',
        ]);

        return back()->with('status', "Bundle \"{$bundle->title}\" created. You can now select it to upload papers.");
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
