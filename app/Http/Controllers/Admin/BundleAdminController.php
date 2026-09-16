<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Services\Payments\EntitlementBackfill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);
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
