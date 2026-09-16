<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Programme;
use App\Models\Setting;
use App\Services\BundlePricing;
use App\Services\Payments\PaymentManager;
use App\Services\ProgrammeSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Settings: the platform bundle price, programme visibility
 * toggles, plus full programme management — add, rename, delete — port
 * of the Next.js settings page and its admin-actions. Students only see
 * the tracks switched on here (catalog, courses, materials, downloads).
 */
class SettingsAdminController extends Controller
{
    public function index(PaymentManager $payments)
    {
        $programmes = ProgrammeSettings::allWithVisibility();

        // Published-content counts per track (port of
        // getProgrammeContentCounts): bundles sold live and free materials.
        $counts = Programme::query()
            ->withCount(['bundles as published_bundles_count' => fn ($q) => $q->where('status', 'PUBLISHED')])
            ->withCount(['resources as published_materials_count' => fn ($q) => $q
                ->where('status', 'PUBLISHED')
                ->whereNull('bundle_id')])
            ->get()
            ->mapWithKeys(fn (Programme $p) => [$p->id => [
                'bundles' => $p->published_bundles_count,
                'materials' => $p->published_materials_count,
            ]]);

        return view('admin.settings', [
            // Read-only view of the live gateway wiring: which driver is
            // selected, whether it is ready, and the exact URLs Paystack
            // needs. Secrets stay in the environment and are never shown.
            'payments' => $payments->status(),
            'price' => BundlePricing::formatted(),
            'bundleCount' => Bundle::count(),
            'outOfSyncCount' => BundlePricing::outOfSyncCount(),
            'programmes' => $programmes,
            'counts' => $counts,
            'deletable' => fn (Programme $p): bool =>
                $p->bundles()->count() === 0 && $p->resources()->count() === 0,
        ]);
    }

    /**
     * Persist the single platform bundle price (cedis in, integer
     * pesewas stored). Every bundle follows it — there is nothing
     * per-bundle left to edit on the Bundles page.
     */
    public function updatePricing(Request $request)
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:'.BundlePricing::MIN_CEDIS, 'max:'.BundlePricing::MAX_CEDIS],
        ]);

        $pesewas = (int) round(((float) $data['price']) * 100);
        BundlePricing::set($pesewas);

        return back()->with('status', sprintf(
            'Bundle price set to GH₵%s — every bundle now sells for that amount.',
            number_format($pesewas / 100, 2),
        ));
    }

    public function toggle(Request $request, Programme $programme)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        ProgrammeSettings::setEnabled($programme->slug, (bool) $data['enabled']);

        return back()->with('status', sprintf(
            '%s is now %s to students.',
            $programme->name,
            $data['enabled'] ? 'VISIBLE' : 'HIDDEN',
        ));
    }

    /**
     * Add a new programme track (port of addProgrammeAction). The slug is
     * generated from the name; the track starts ENABLED so it appears in
     * the student browse flow right away. Upsert keeps repeated submits
     * safe (name and slug are both unique).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $name = trim($data['name']);
        $slug = Programme::slugify($name);
        if ($slug === '') {
            return back()->with('error', 'Could not generate a URL slug from that name.');
        }

        $programme = Programme::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name],
        );
        ProgrammeSettings::setEnabled($programme->slug, true);

        return back()->with('status', "\"{$programme->name}\" added and visible to students.");
    }

    /**
     * Rename a programme track (port of editProgrammeAction). Updates the
     * name and regenerates the slug; if the slug changed, the visibility
     * setting key is migrated so the track keeps its on/off state.
     */
    public function rename(Request $request, Programme $programme)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $newName = trim($data['name']);
        $newSlug = Programme::slugify($newName);
        if ($newSlug === '') {
            return back()->with('error', 'Could not generate a URL slug from that name.');
        }

        // Nothing to do if the name didn't change.
        if ($programme->name === $newName && $programme->slug === $newSlug) {
            return back()->with('status', 'Programme updated.');
        }

        // Slugs are unique: refuse if another track already owns it.
        $taken = Programme::where('slug', $newSlug)->where('id', '!=', $programme->id)->exists();
        if ($taken) {
            return back()->with('error', "\"{$newName}\" would reuse the URL slug of an existing programme.");
        }

        DB::transaction(function () use ($programme, $newName, $newSlug) {
            if ($programme->slug !== $newSlug) {
                $oldKey = ProgrammeSettings::settingKey($programme->slug);
                $newKey = ProgrammeSettings::settingKey($newSlug);
                $existing = Setting::where('key', $oldKey)->value('value');

                Setting::where('key', $oldKey)->delete();
                Setting::updateOrCreate(
                    ['key' => $newKey],
                    ['value' => $existing ?? 'true'],
                );
            }

            $programme->update(['name' => $newName, 'slug' => $newSlug]);
        });

        return back()->with('status', "\"{$newName}\" was updated.");
    }

    /**
     * Delete a programme track (port of deleteProgrammeAction). Only
     * allowed when it has no bundles or papers attached — hiding content
     * is what the visibility toggle is for. The outcome is never a silent
     * no-op: blocked deletes carry the reason back to the page.
     */
    public function destroy(Programme $programme)
    {
        $bundles = $programme->bundles()->count();
        $resources = $programme->resources()->count();

        if ($bundles > 0 || $resources > 0) {
            return back()->with('error', sprintf(
                'Couldn’t delete "%s" — it still has %d bundle(s) and %d paper(s) attached. Move or remove that content first, or just toggle the track off instead.',
                $programme->name,
                $bundles,
                $resources,
            ));
        }

        $name = $programme->name;

        DB::transaction(function () use ($programme) {
            Setting::where('key', ProgrammeSettings::settingKey($programme->slug))->delete();
            $programme->delete();
        });

        return back()->with('status', "Programme \"{$name}\" was deleted.");
    }

}
