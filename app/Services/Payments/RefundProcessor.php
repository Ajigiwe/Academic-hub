<?php

namespace App\Services\Payments;

use App\Models\Entitlement;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Format;
use App\Support\NotificationCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds the gateway processed (Paystack drives these from its
 * dashboard or its API).
 *
 * Same rules as the charge path:
 *
 *  - never act on the payload alone — the refund is re-fetched from the
 *    gateway's API and only a `PROCESSED` refund changes anything;
 *  - the gateway's refund id is the idempotency key, so a replayed
 *    delivery (or a pending → processing → processed sequence) updates
 *    one row instead of refunding twice;
 *  - money state is recorded first, then access follows: a FULL refund
 *    flips the order and payment to REFUNDED, revokes the papers the
 *    order unlocked, and tells the student; a PARTIAL refund is recorded
 *    and the student keeps access — they still paid for part of it and
 *    papers are not divisible.
 */
class RefundProcessor
{
    /**
     * @param array<string, mixed> $body the verified webhook body
     *
     * @return array{status: string, order_reference: ?string, recorded: bool, revoked: int, full: bool, unmatched: bool, already_processed: bool}
     */
    public function process(PaymentProvider $provider, array $body): array
    {
        $delivery = $provider->refundFrom($body);

        if (! $delivery) {
            throw new \RuntimeException('Refund delivery does not carry a refund id.');
        }

        // Ask the gateway, don't trust the delivery.
        $snapshot = $provider->verifyRefund($delivery['refund_id']);

        $order = $this->resolveOrder(
            $snapshot['reference'] ?? null,
            $snapshot['transaction_id'] ?? null,
            $delivery['reference'] ?? null,
            $delivery['transaction_id'] ?? null,
        );

        if (! $order) {
            // A refund we cannot match (another environment's keys, or a
            // transaction from a different deployment). The money is gone
            // either way, and retrying will not conjure the order — ACK it
            // and leave a trail rather than retry for 72 hours.
            Log::warning('Refund acknowledged but no matching order was found.', [
                'provider' => $provider->name(),
                'refund_id' => $delivery['refund_id'],
                'reference' => $snapshot['reference'] ?? $delivery['reference'],
                'transaction_id' => $snapshot['transaction_id'] ?? $delivery['transaction_id'],
            ]);

            return $this->result($snapshot['status'], null, recorded: false, revoked: 0, full: false, unmatched: true);
        }

        return DB::transaction(function () use ($provider, $delivery, $snapshot, $order) {
            $payment = $order->payments()->first();
            $status = $snapshot['status'];

            $attributes = [
                'order_id' => $order->id,
                'payment_id' => $payment?->id,
                'provider' => $provider->name(),
                'provider_ref' => $delivery['refund_id'],
                'status' => $status,
                'amount_pesewas' => $snapshot['amount_pesewas'] ?: (int) ($delivery['amount_pesewas'] ?? 0),
                'currency' => $snapshot['currency'] ?? $delivery['currency'] ?? $order->currency,
                'reason' => $snapshot['merchant_note'] ?? $delivery['merchant_note'] ?? null,
                'processed_at' => $status === 'PROCESSED' ? now() : null,
            ];

            $existing = Refund::where('provider', $provider->name())
                ->where('provider_ref', $delivery['refund_id'])
                ->lockForUpdate()
                ->first();

            // Only the transition INTO PROCESSED may flip money state,
            // revoke access, or notify — a redelivery must not repeat any
            // of it.
            $alreadyProcessed = $existing && $existing->status === 'PROCESSED';

            // Refunds move forward only. A late `refund.processing`
            // arriving after `refund.processed` must not demote a row the
            // money state already reflects.
            if ($alreadyProcessed && $status !== 'PROCESSED') {
                $status = 'PROCESSED';
                $attributes['status'] = 'PROCESSED';
                $attributes['processed_at'] = $existing->processed_at ?? now();
            }

            if ($existing) {
                $existing->update($attributes);
                $refund = $existing;
            } else {
                $refund = Refund::create($attributes);
            }

            if ($status !== 'PROCESSED') {
                return $this->result($status, $order->reference, recorded: true, revoked: 0, full: false, unmatched: false);
            }

            // Sum processed money INCLUDING the row just written.
            $refunded = (int) $order->refunds()->processed()->sum('amount_pesewas');
            $full = $refunded >= $order->amount_pesewas;

            $order->update(['status' => $full ? 'REFUNDED' : 'PARTIALLY_REFUNDED']);
            $payment?->update(['status' => $full ? 'REFUNDED' : 'REFUNDED_PARTIALLY']);

            $revoked = 0;
            if ($full) {
                // Revoke what THIS order unlocked. The entitlement engine
                // also has a bundle-ownership fallback, which keys off
                // active rows — so revoking here really does close access.
                $revoked = Entitlement::where('order_id', $order->id)
                    ->where('status', 'active')
                    ->update(['status' => 'revoked', 'revoked_at' => now()]);
            }

            if (! $alreadyProcessed) {
                $this->notify($order, $refund, $full, $refunded);
            }

            return $this->result(
                $status,
                $order->reference,
                recorded: true,
                revoked: $revoked,
                full: $full,
                unmatched: false,
                alreadyProcessed: (bool) $alreadyProcessed,
            );
        });
    }

    private function notify(Order $order, Refund $refund, bool $full, int $refundedPesewas): void
    {
        $amount = Format::money($refund->amount_pesewas);

        Notification::create([
            'user_id' => $order->user_id,
            'type' => 'PAYMENT',
            'title' => $full ? 'Refund processed' : 'Partial refund processed',
            'body' => $full
                ? "{$amount} for order {$order->reference} has been refunded to your original payment method. The papers from this order are no longer in My Library."
                : "{$amount} of ".Format::money($order->amount_pesewas)." for order {$order->reference} has been refunded to your original payment method. Your papers stay available.",
            'url' => NotificationCopy::receiptPath($order->reference, refunded: true),
        ]);
    }

    /**
     * Find the order a refund belongs to. Newest hints first: the
     * reference the gateway reported, then the gateway's transaction id
     * we stored at payment time, then whatever the delivery claimed.
     */
    private function resolveOrder(?string ...$hints): ?Order
    {
        $candidates = array_values(array_filter($hints, fn ($hint) => is_string($hint) && $hint !== ''));

        foreach ($candidates as $candidate) {
            $order = Order::where('reference', $candidate)->first();
            if ($order) {
                return $order;
            }
        }

        foreach ($candidates as $candidate) {
            $payment = Payment::where('provider_ref', $candidate)
                ->orWhere('provider_txn_id', $candidate)
                ->first();
            if ($payment) {
                return Order::find($payment->order_id);
            }
        }

        return null;
    }

    private function result(
        string $status,
        ?string $orderReference,
        bool $recorded,
        int $revoked,
        bool $full,
        bool $unmatched,
        bool $alreadyProcessed = false,
    ): array {
        return [
            'status' => $status,
            'order_reference' => $orderReference,
            'recorded' => $recorded,
            'revoked' => $revoked,
            'full' => $full,
            'unmatched' => $unmatched,
            'already_processed' => $alreadyProcessed,
        ];
    }
}
