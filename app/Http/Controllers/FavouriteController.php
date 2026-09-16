<?php

namespace App\Http\Controllers;

use App\Models\Favourite;
use App\Models\Resource;
use Illuminate\Http\Request;

/**
 * Favourites — bookmark a paper or free material. Toggle semantics,
 * idempotent by the composite (user_id, resource_id) key. Guests are
 * redirected to login; suspended accounts are blocked like everywhere
 * else via the auth chain.
 */
class FavouriteController extends Controller
{
    public function index(Request $request)
    {
        $favourites = Favourite::where('user_id', $request->user()->id)
            ->with('resource.course')
            ->latest('created_at')
            ->get();

        return view('favourites.index', ['favourites' => $favourites]);
    }

    public function toggle(Request $request, Resource $resource)
    {
        // Only published content can be bookmarked.
        $exists = Resource::whereKey($resource->id)->published()->exists();
        if (! $exists) {
            abort(404);
        }

        $deleted = Favourite::where('user_id', $request->user()->id)
            ->where('resource_id', $resource->id)
            ->delete();

        if ($deleted === 0) {
            Favourite::firstOrCreate([
                'user_id' => $request->user()->id,
                'resource_id' => $resource->id,
            ]);
        }

        return back()->with('status', $deleted
            ? 'Removed from your saved items.'
            : 'Saved to your library favourites.');
    }
}
