<?php

namespace App\Services\Viewer;

use App\Models\{AccessLog, Entitlement, Order, RecentlyViewed, Resource, ResourceFile, User, ViewingSession};
use Illuminate\Http\Request;

/**
 * Viewer authorization chain (spec §26): authenticated → entitled →
 * viewing session. This is the ONLY door into the viewer; every reader
 * path runs the full chain here.
 */
class ViewerService
{
    /** Re-auth chain every 10 min (mirrors SESSION_FRESH_WINDOW_MS). */
    public const SESSION_FRESH_WINDOW_MINUTES = 10;

    public function __construct(private readonly \App\Services\Entitlements $entitlements) {}

    /** Delegated entitlement check (the single source of truth). */
    public function hasEntitlement(User $user, int $resourceId): bool
    {
        return $this->entitlements->has($user, $resourceId);
    }

    public function requestMeta(Request $request): array
    {
        $ip = $request->headers->get('x-forwarded-for');
        $ip = $ip ? trim(explode(',', $ip)[0]) : $request->ip();

        return [
            'ip_address' => $ip,
            'user_agent' => (string) $request->userAgent(),
        ];
    }

    /** Fire-and-forget audit logging (spec §27) — never blocks a request. */
    public function logAccess(
        ?int $userId,
        ?int $resourceId,
        string $action,
        ?string $detail = null,
        ?array $meta = null,
    ): void {
        try {
            AccessLog::create([
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'action' => $action,
                'detail' => $detail,
                ...($meta ?? []),
            ]);
        } catch (\Throwable) {
            // An audit write must not take the app down.
        }
    }

    /**
     * Run the full chain for a resource the user wants to read.
     *
     * @return array{ok: true, resource: Resource, file: ResourceFile, session: ViewingSession, fresh: bool}
     *         |array{ok: false, status: int, message: string}
     */
    public function openForUser(User $user, string $slug, Request $request): array
    {
        $meta = $this->requestMeta($request);

        $resource = Resource::where('slug', $slug)->first();
        if (! $resource) {
            $this->logAccess($user->id, null, 'ACCESS_DENIED', "Viewer session for unknown resource slug: {$slug}", $meta);

            return ['ok' => false, 'status' => 404, 'message' => 'Resource not found.'];
        }

        if (! $this->entitlements->has($user, $resource->id)) {
            $this->logAccess($user->id, $resource->id, 'ACCESS_DENIED', 'Viewer session without entitlement.', $meta);

            return ['ok' => false, 'status' => 403, 'message' => 'You do not have access to this resource.'];
        }

        if ($resource->status === 'ARCHIVED') {
            return ['ok' => false, 'status' => 410, 'message' => 'This resource is currently unavailable.'];
        }

        $file = ResourceFile::where('resource_id', $resource->id)->where('is_current', true)
            ->orderByDesc('created_at')->first();
        if (! $file) {
            return ['ok' => false, 'status' => 404, 'message' => 'No file attached to this resource yet.'];
        }

        $entitlement = Entitlement::where('user_id', $user->id)
            ->where('resource_id', $resource->id)->where('status', 'active')->first();

        $session = ViewingSession::create([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'entitlement_id' => $entitlement?->id,
            'ip_address' => $meta['ip_address'],
            'user_agent' => $meta['user_agent'],
        ]);

        // Best-effort "recently viewed" upsert (spec §12). Atomic upsert
        // avoids any model-instantiation id fetch on the composite key.
        try {
            RecentlyViewed::upsert(
                ['user_id' => $user->id, 'resource_id' => $resource->id, 'viewed_at' => now()],
                ['user_id', 'resource_id'],
                ['viewed_at'],
            );
        } catch (\Throwable) {
        }

        $this->logAccess($user->id, $resource->id, 'STARTED_VIEWER', "Viewing session {$session->id}", $meta);

        return ['ok' => true, 'resource' => $resource, 'file' => $file, 'session' => $session, 'fresh' => true];
    }

    /**
     * Identity burned into the document: name · account id · order ref.
     * Bulk model — the purchase is a BUNDLE; find the paid order whose
     * bundle contains this paper.
     *
     * @return array{name: string, account_id: string, order_ref: string}
     */
    public function identityFor(User $user, Resource $resource): array
    {
        $orderRef = Order::where('user_id', $user->id)->where('status', 'PAID')
            ->whereHas('items.bundle.resources', fn ($q) => $q->where('resources.id', $resource->id))
            ->orderByDesc('paid_at')
            ->value('reference') ?? '—';

        return [
            'name' => trim($user->first_name.' '.$user->last_name),
            'account_id' => strtoupper(substr((string) $user->id, -6)),
            'order_ref' => $orderRef,
        ];
    }

    /** Session lookup by id + resource, ignoring freshness (caller distinguishes 403 vs 428). */
    public function sessionByIdFor(int $sessionId, int $resourceId): ?ViewingSession
    {
        return ViewingSession::where('id', $sessionId)
            ->where('resource_id', $resourceId)
            ->whereNull('ended_at')
            ->first();
    }

    /** Live-session check: the session must belong to the user and be fresh. */
    public function liveSessionFor(int $sessionId, int $userId, int $resourceId): ?ViewingSession
    {
        return ViewingSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('resource_id', $resourceId)
            ->whereNull('ended_at')
            ->where('started_at', '>=', now()->subMinutes(self::SESSION_FRESH_WINDOW_MINUTES))
            ->first();
    }
}
