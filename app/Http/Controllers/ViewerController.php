<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Services\Documents\{DocumentStore, PdfWatermarker};
use App\Services\Viewer\ViewerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Secure viewer (spec §13/§26).
 *
 * The client NEVER receives the original PDF. GET /view/{slug} runs the
 * authorization chain (authenticated → entitled → session) and renders
 * the reading page. The page embeds a SIGNED TEMPORARY URL bound to
 * user + session, short expiry; the document endpoint re-checks the
 * signature, the live session, and re-stamps the per-user watermark.
 */
class ViewerController extends Controller
{
    public function __construct(
        private readonly ViewerService $viewer,
        private readonly DocumentStore $store,
        private readonly PdfWatermarker $watermarker,
    ) {}

    public function show(string $slug, Request $request)
    {
        $result = $this->viewer->openForUser($request->user(), $slug, $request);

        if (! $result['ok']) {
            abort($result['status'], $result['message']);
        }

        $resource = $result['resource'];
        $session = $result['session'];

        // Signed temporary URL: bound to user + resource + session, short
        // expiry. Replaces the Next.js per-page HMAC page-token scheme —
        // Laravel signs the full path+params and expires it.
        $documentUrl = URL::temporarySignedRoute(
            'viewer.document',
            now()->addSeconds(config('viewer.url_ttl_seconds')),
            ['resource' => $resource->id, 'session' => $session->id],
        );

        return view('viewer', [
            'resource' => $resource,
            'documentUrl' => $documentUrl,
        ]);
    }

    /**
     * Hand the student a FRESH signed document URL.
     *
     * This is the reader's "open full screen" action, and it is the way
     * in on browsers that cannot display a PDF inside a page — Chrome on
     * Android blocks embedded PDFs outright ("This page has been blocked
     * by Chrome"), so the embed can never be the only path to the paper.
     *
     * The full authorization chain runs again here, and the returned URL
     * is minted for the new session rather than reusing a stale one.
     */
    public function open(string $slug, Request $request)
    {
        $result = $this->viewer->openForUser($request->user(), $slug, $request);

        if (! $result['ok']) {
            abort($result['status'], $result['message']);
        }

        return redirect()->to(URL::temporarySignedRoute(
            'viewer.document',
            now()->addSeconds(config('viewer.url_ttl_seconds')),
            ['resource' => $result['resource']->id, 'session' => $result['session']->id],
        ));
    }

    /**
     * The same fresh URL, as JSON — the in-page reader calls this when
     * the signed link it was holding lapses, so a long read continues
     * where it was instead of failing.
     */
    public function documentUrl(string $slug, Request $request)
    {
        $result = $this->viewer->openForUser($request->user(), $slug, $request);

        if (! $result['ok']) {
            return response()->json(['error' => $result['message']], $result['status']);
        }

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'viewer.document',
                now()->addSeconds(config('viewer.url_ttl_seconds')),
                ['resource' => $result['resource']->id, 'session' => $result['session']->id],
            ),
            'expires_in' => (int) config('viewer.url_ttl_seconds'),
        ]);
    }

    public function document(Request $request, Resource $resource, int $session)
    {
        $user = $request->user();

        // The signature already proves this URL was minted by us and has
        // not expired. Re-run the authorization chain on every request —
        // a leaked URL must not outlive the session or the entitlement.
        if (! $user) {
            abort(401, 'Authentication required.');
        }

        if ($resource->status === 'ARCHIVED') {
            abort(410, 'This resource is currently unavailable.');
        }

        if (! $this->viewer->hasEntitlement($user, $resource->id)) {
            $this->viewer->logAccess(
                $user->id,
                $resource->id,
                'ACCESS_DENIED',
                'Document fetch without entitlement.',
                $this->viewer->requestMeta($request),
            );
            abort(403, 'You do not have access to this resource.');
        }

        // Distinguish a foreign session (403) from an expired own one (428).
        $sessionRow = $this->viewer->sessionByIdFor($session, $resource->id);
        if ($sessionRow && $sessionRow->user_id !== $user->id) {
            $this->viewer->logAccess(
                $user->id,
                $resource->id,
                'ACCESS_DENIED',
                "Document fetch with foreign session {$session}.",
                $this->viewer->requestMeta($request),
            );
            abort(403, 'You do not have access to this resource.');
        }

        $liveSession = $this->viewer->liveSessionFor($session, $user->id, $resource->id);
        if (! $liveSession) {
            abort(428, 'Viewer session expired. Reload the page.');
        }

        $file = $resource->files()->where('is_current', true)->orderByDesc('created_at')->first();
        if (! $file) {
            abort(404, 'No file attached to this resource yet.');
        }

        $identity = $this->viewer->identityFor($user, $resource);

        // Per-user stamped document. Cache key includes the account id —
        // rendered bytes are NEVER shared between users.
        $cacheKey = "wm_{$identity['account_id']}_{$file->id}";
        $bytes = $this->store->getCached($cacheKey);
        if ($bytes === null) {
            $bytes = $this->watermarker->watermark($this->store->get($file->storage_key), $identity);
            $this->store->putCached($cacheKey, $bytes);
        }

        $liveSession->update(['last_seen_at' => now()]);
        $this->viewer->logAccess($user->id, $resource->id, 'PAGE_VIEWED', "Document · session {$liveSession->id}", $this->viewer->requestMeta($request));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'Cache-Control' => 'no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
