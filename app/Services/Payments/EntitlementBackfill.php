<?php

namespace App\Services\Payments;

use App\Models\Entitlement;
use App\Models\Resource;

/**
 * Bulk-model backfill (port of grantBundlePapersToPastBuyers): grants
 * every PUBLISHED paper in a bundle to everyone who has already PAID
 * for that bundle.
 *
 * Papers uploaded after a purchase ("late uploads") have no entitlement
 * row at sale time, so they never reach past buyers' libraries. Run
 * this after any transition that publishes papers inside a sold bundle;
 * it is idempotent and only fills gaps (unique (user_id, resource_id)
 * constraint is the arbiter).
 */
class EntitlementBackfill
{
    /**
     * @return int number of entitlements created
     */
    public function grantBundlePapersToPastBuyers(int $bundleId): int
    {
        // Past buyers = users holding >=1 active entitlement on a paper in
        // this bundle. Keep their EARLIEST bundle entitlement — that
        // anchors the original order.
        $firstRows = Entitlement::selectRaw('MIN(id) as id')
            ->whereHas('resource', fn ($q) => $q->where('bundle_id', $bundleId))
            ->where('status', 'active')
            ->groupBy('user_id')
            ->pluck('id');

        if ($firstRows->isEmpty()) {
            return 0;
        }

        $buyers = Entitlement::whereIn('id', $firstRows)
            ->with(['order'])
            ->get();

        $publishedIds = Resource::where('bundle_id', $bundleId)
            ->where('status', 'PUBLISHED')
            ->pluck('id');
        if ($publishedIds->isEmpty()) {
            return 0;
        }

        $covered = Entitlement::whereIn('user_id', $buyers->pluck('user_id'))
            ->whereIn('resource_id', $publishedIds)
            ->where('status', 'active')
            ->get(['user_id', 'resource_id'])
            ->map(fn ($e) => $e->user_id.':'.$e->resource_id)
            ->flip();

        $created = 0;
        foreach ($buyers as $buyer) {
            // Backdate to the original purchase so the library reads naturally.
            $purchasedAt = $buyer->order?->paid_at ?? $buyer->order?->created_at ?? now();

            $rows = [];
            foreach ($publishedIds as $resourceId) {
                if ($covered->has($buyer->user_id.':'.$resourceId)) {
                    continue; // only fill gaps — never clobber existing rows
                }
                $rows[] = [
                    'user_id' => $buyer->user_id,
                    'resource_id' => $resourceId,
                    'order_id' => $buyer->order_id,
                    'purchased_at' => $purchasedAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows !== []) {
                // skipDuplicates: concurrent backfills converge safely.
                $created += Entitlement::insertOrIgnore($rows);
            }
        }

        return $created;
    }
}
