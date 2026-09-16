<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Verify a payment against the gateway's own API and fulfill the order.
 * This is the ONLY place fulfillment is triggered from, and it always
 * re-checks with the provider server-side (spec §11: "never trust the
 * frontend payment-success state"). Used by both the webhook and the
 * verify-callback.
 */
class PaymentProcessor
{
    public function __construct(
        private readonly PaymentManager $providers,
        private readonly OrderFulfiller $fulfiller,
    ) {}

    /**
     * @return array{fulfilled: bool, already_fulfilled: bool}
     */
    public function processSuccessfulPayment(string $orderRef, ?string $providerName = null): array
    {
        // Webhook callers pass the DELIVERING provider's name so the
        // server-to-server verification talks to the same gateway that
        // reported success. Defaults to the configured driver (the
        // browser-return verify path).
        $provider = $providerName !== null
            ? ($this->providers->byName($providerName) ?? $this->providers->provider())
            : $this->providers->provider();
        $verified = $provider->verifyPayment($orderRef);

        $order = Order::where('reference', $orderRef)->with('items')->first();
        if (! $order) {
            throw new \RuntimeException("Order not found: {$orderRef}");
        }

        // A refunded sale is CLOSED. The charge did succeed once — a stale
        // browser return or a retried delivery would otherwise mark it
        // paid again and undo the refund. Nothing to do, and no error: the
        // money state is already where it should be.
        if (in_array($order->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            return ['fulfilled' => false, 'already_fulfilled' => true];
        }

        if (! $verified['successful']) {
            // In flight (mobile money awaiting operator confirmation): the
            // money is not ours yet, but nothing has failed either — leave
            // the payment PENDING so the webhook can still settle it, and
            // tell the caller to report "still confirming" rather than a
            // refusal. Never fulfill on a pending verification.
            if ($verified['pending'] ?? false) {
                throw new PaymentPendingException(
                    $orderRef,
                    $verified['failure_reason'] ?? 'Awaiting confirmation from the gateway.',
                );
            }

            Payment::where('order_id', $order->id)->where('status', 'PENDING')->update([
                'status' => 'FAILED',
                'failure_reason' => $verified['failure_reason'] ?? 'Verification failed.',
            ]);
            throw new \RuntimeException($verified['failure_reason'] ?? 'Payment verification failed.');
        }

        // Amount/currency tamper check against our own order record.
        if (
            $verified['amount_pesewas'] !== $order->amount_pesewas
            || $verified['currency'] !== $order->currency
        ) {
            Payment::where('order_id', $order->id)->where('status', 'PENDING')->update([
                'status' => 'FAILED',
                'failure_reason' => sprintf(
                    'Amount mismatch: expected %d %s, got %d %s.',
                    $order->amount_pesewas, $order->currency,
                    $verified['amount_pesewas'], $verified['currency'],
                ),
            ]);
            throw new \RuntimeException('Payment amount mismatch.');
        }

        // Anything not already SUCCESSFUL is settled by this verification.
        // Late money must be able to recover a row an earlier callback
        // marked FAILED (the student authorised after we asked), otherwise
        // the order would end up PAID next to a FAILED payment.
        Payment::where('order_id', $order->id)->where('status', '!=', 'SUCCESSFUL')->update([
            'status' => 'SUCCESSFUL',
            'provider_ref' => $verified['provider_ref'],
            'provider_txn_id' => $verified['provider_txn_id'] ?? null,
            'channel' => $verified['channel'],
            'failure_reason' => null,
            'verified_at' => now(),
        ]);

        $fulfillment = $this->fulfiller->fulfill($orderRef);

        return [
            'fulfilled' => ! $fulfillment['already_fulfilled'],
            'already_fulfilled' => $fulfillment['already_fulfilled'],
        ];
    }
}
