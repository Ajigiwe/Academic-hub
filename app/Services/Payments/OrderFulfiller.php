<?php

namespace App\Services\Payments;

use App\Models\Entitlement;
use App\Models\Order;
use App\Models\Resource;
use App\Services\Notifications;
use Illuminate\Support\Facades\DB;

/**
 * Single fulfillment path shared by webhook and verify-callback.
 * Idempotent: re-running for a paid order is a no-op.
 *
 * Items reference BUNDLES (the unit of sale); fulfillment expands each
 * bundle into entitlements for every paper inside it.
 */
class OrderFulfiller
{
    /**
     * @return array{order_id: int, order_reference: string, entitlement_resource_ids: list<int>, already_fulfilled: bool}
     */
    public function fulfill(string $reference): array
    {
        return DB::transaction(function () use ($reference) {
            // FOR UPDATE lock: webhook + verify-callback racing on the same
            // order serialize here; the loser sees PAID and exits as no-op.
            $order = Order::where('reference', $reference)->lockForUpdate()->first();

            if (! $order) {
                throw new \RuntimeException("Order not found: {$reference}");
            }

            $bundleIds = $order->items->pluck('bundle_id')->unique()->values();
            $resourceIds = $bundleIds->isEmpty() ? collect() : Resource::whereIn('bundle_id', $bundleIds)->pluck('id');

            // Refunded orders are terminal too: re-fulfilling one would
            // hand back content the student was refunded for.
            if (in_array($order->status, ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'], true) || $resourceIds->isEmpty()) {
                return $this->result($order, $resourceIds, alreadyFulfilled: true);
            }

            // CAS-style guard (mirrors updateMany where status not in
            // paid/refunded): re-check under lock so the winner is decided
            // transactionally.
            if (Order::where('id', $order->id)
                ->whereNotIn('status', ['PAID', 'REFUNDED', 'PARTIALLY_REFUNDED'])
                ->update([
                    'status' => 'PAID',
                    'paid_at' => now(),
                ]) === 0) {
                return $this->result($order, $resourceIds, alreadyFulfilled: true);
            }
            $order->refresh();

            foreach ($resourceIds as $resourceId) {
                $this->grantEntitlement($order->user_id, $resourceId, $order->id);
            }

            // The confirmation names the papers and links to the receipt
            // (see App\Services\Notifications), so the bell is a way in
            // rather than a note the student has to act on themselves.
            $order->loadMissing('items.bundle');
            Notifications::purchase($order);

            return $this->result($order, $resourceIds, alreadyFulfilled: false);
        }, attempts: 3);
    }

    /**
     * Grant an entitlement exactly once (spec §11). Safe to call from
     * webhook and verify paths concurrently — the unique (user_id,
     * resource_id) constraint is the arbiter.
     *
     * @return 'created'|'existing'
     */
    public function grantEntitlement(int $userId, int $resourceId, int $orderId): string
    {
        try {
            Entitlement::create([
                'user_id' => $userId,
                'resource_id' => $resourceId,
                'order_id' => $orderId,
                'status' => 'active',
            ]);

            return 'created';
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent grant already won.
            return 'existing';
        }
    }

    private function result(Order $order, $resourceIds, bool $alreadyFulfilled): array
    {
        return [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'entitlement_resource_ids' => $resourceIds->all(),
            'already_fulfilled' => $alreadyFulfilled,
        ];
    }
}
