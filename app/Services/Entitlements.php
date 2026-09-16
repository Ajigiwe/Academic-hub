<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Resource;
use App\Models\User;

/**
 * Entitlement engine (spec §26) — the single source of truth for "may
 * this user open this resource?". Every reader path must go through
 * here; no page or route may make its own entitlement decision.
 *
 * Bulk model: a paid bundle unlocks every paper inside it. Entitlements
 * are granted per-resource at purchase time, AND the ownership check
 * falls back to "the user paid for a bundle containing this resource" —
 * so papers added to a bundle after the purchase are unlocked too.
 */
class Entitlements
{
    public function has(User $user, int $resourceId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $direct = Entitlement::where('user_id', $user->id)
            ->where('resource_id', $resourceId)
            ->where('status', 'active')
            ->exists();
        if ($direct) {
            return true;
        }

        // Bundle-ownership fallback.
        $bundleId = Resource::where('id', $resourceId)->value('bundle_id');
        if (! $bundleId) {
            return false;
        }

        return Entitlement::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('order.items', fn ($q) => $q->where('bundle_id', $bundleId))
            ->exists();
    }
}
