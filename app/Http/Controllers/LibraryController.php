<?php

namespace App\Http\Controllers;

use App\Models\Entitlement;
use App\Models\Favourite;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // One entitlement per owned paper (the Next.js library lists
        // entitlements, not bundle groupings), newest purchase first.
        $entitlements = Entitlement::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['resource.bundle.course', 'resource.files', 'order'])
            ->orderByDesc('purchased_at')
            ->get()
            ->filter(fn ($e) => $e->resource)
            ->values();

        // Favourites feed the saved-materials section (saved free
        // materials are bookmarked, never purchased).
        $savedIds = Favourite::where('user_id', $user->id)->pluck('resource_id');
        $savedMaterials = $savedIds->isEmpty() ? collect() : \App\Models\Resource::published()
            ->whereNull('bundle_id')
            ->whereIn('id', $savedIds)
            ->with(['course', 'files'])
            ->orderBy('title')
            ->get();

        return view('library', [
            'entitlements' => $entitlements,
            'savedMaterials' => $savedMaterials,
            'savedIds' => $savedIds,
        ]);
    }
}
