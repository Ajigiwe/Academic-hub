<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\WebhookEvent;

/**
 * Sandbox gateway for development — no real money. Simulates a hosted
 * checkout page + server-to-server webhook so the swap to Paystack
 * later changes zero call sites.
 *
 * The Next.js version kept approvals in an in-memory Map with a
 * webhook-events fallback for serverless. PHP has no such constraint:
 * the pending transaction is persisted in the DB, and the webhook row
 * still serves as the durable cross-request acknowledgment — same
 * semantics, simpler mechanics.
 */
class MockProvider implements PaymentProvider
{
    /** The shared dev secret shipped in config/payments.php. */
    public const DEV_SECRET = 'dev-only-webhook-secret';

    public function name(): string
    {
        return 'mock';
    }

    /**
     * The sandbox can take orders only when it has a webhook secret, and
     * in a LIVE environment that secret must not be the repository's
     * shared dev default — it is public, so anyone could sign a
     * `payment.success` and unlock a bundle for free.
     */
    public function isConfigured(): bool
    {
        $secret = trim((string) config('payments.webhook_secret'));
        if ($secret === '') {
            return false;
        }

        return ! (app()->isProduction() && $secret === self::DEV_SECRET);
    }

    public function mode(): string
    {
        return 'sandbox';
    }

    // ── Webhook scheme ───────────────────────────────────────────────

    public function signatureHeader(): string
    {
        return 'x-pastq-signature';
    }

    /** HMAC-SHA256 over the raw body, timing-safe compare. */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        // An unconfigured sandbox accepts nothing — a forged delivery in
        // production must not survive on the repo's dev secret.
        if (! $this->isConfigured()) {
            return false;
        }

        $secret = (string) config('payments.webhook_secret');
        if ($signature === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $rawBody, $secret),
            $signature,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    public function eventId(string $event, array $body): string
    {
        // The mock supplies an explicit eventId; fall back deterministically.
        $explicit = $body['eventId'] ?? null;

        return is_string($explicit) && $explicit !== ''
            ? $explicit
            : "mock:{$event}:".(string) ($body['reference'] ?? 'unknown');
    }

    /**
     * @param array<string, mixed> $body
     */
    public function referenceFrom(array $body): ?string
    {
        $reference = $body['reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    public function isFulfillableEvent(string $event): bool
    {
        return $event === 'payment.success';
    }

    // ── Refunds ──────────────────────────────────────────────────────

    /**
     * The sandbox never refunds: it has no dashboard behind it and no
     * money to give back. Refunds are a real-gateway concern, so a
     * refund-shaped delivery to the mock is nothing to act on.
     */
    public function isRefundEvent(string $event): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function refundFrom(array $body): ?array
    {
        return null;
    }

    public function verifyRefund(string $refundId): array
    {
        throw new \RuntimeException('The sandbox gateway does not process refunds.');
    }

    public function initiatePayment(
        string $orderRef,
        int $amountPesewas,
        string $currency,
        string $customerEmail,
        string $callbackUrl,
        string $origin,
    ): array {
        // Note: unlike the Next.js mock, this doesn't need to pre-store
        // anything — verification relies on the durable Payment row +
        // webhook acknowledgment (see verifyPayment).
        return [
            'redirect_url' => sprintf(
                '%s/payment/mock-checkout?ref=%s&amount=%d&callback=%s',
                rtrim($origin, '/'),
                urlencode($orderRef),
                $amountPesewas,
                urlencode($callbackUrl),
            ),
            'provider_ref' => "mock_{$orderRef}",
            'channel' => 'MOCK',
        ];
    }

    public function verifyPayment(string $orderRef): array
    {
        $payment = Payment::where('reference', $orderRef)->first();

        if (! $payment) {
            return $this->failure($orderRef, 'Unknown or expired mock transaction.');
        }

        // The signed `payment.success` webhook row is the durable proof
        // that the student approved this reference (the mock gateway
        // wrote it before redirecting). Without it, nothing is verified.
        // (The reference is a denormalized indexed column — querying the
        // JSON payload would be Postgres-specific.)
        $acknowledged = WebhookEvent::where('provider', $this->name())
            ->where('type', 'payment.success')
            ->where('reference', $orderRef)
            ->exists();

        if (! $acknowledged) {
            return $this->failure($orderRef, 'Mock transaction not acknowledged by the gateway.');
        }

        return [
            'successful' => true,
            'pending' => false,
            'provider_ref' => "mock_{$orderRef}",
            'channel' => 'MOCK',
            // Amount comes from OUR payment record — tamper check happens
            // in the processor against the order total.
            'amount_pesewas' => $payment->amount_pesewas,
            'currency' => $payment->currency,
        ];
    }

    private function failure(string $orderRef, string $reason): array
    {
        return [
            'successful' => false,
            // The sandbox settles instantly — nothing is ever in flight.
            'pending' => false,
            'provider_ref' => "mock_{$orderRef}",
            'channel' => 'MOCK',
            'amount_pesewas' => 0,
            'currency' => 'GHS',
            'failure_reason' => $reason,
        ];
    }
}
