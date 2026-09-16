<?php

namespace App\Services\Payments;

/**
 * Payment abstraction (spec §11): the checkout flow depends on this
 * interface only, so providers (mock ↔ Paystack) are swappable without
 * touching order/entitlement logic.
 *
 * Each provider also owns its WEBHOOK SCHEME: the signature header it
 * signs with, the HMAC variant, the event-id format that feeds the
 * shared dedupe arbiter, and which events trigger fulfillment. The
 * single webhook endpoint resolves the scheme from the delivery's
 * header, so all providers share the dedupe + fulfillment chain.
 */
interface PaymentProvider
{
    public function name(): string;

    /**
     * True when this provider has everything it needs to move money.
     * Paystack needs a secret key; the mock needs a webhook secret that
     * is not the repository's shared dev default in a live environment.
     * A selected-but-unready provider must never silently take orders.
     */
    public function isConfigured(): bool;

    /**
     * Human-facing mode for the admin console: "live"/"test" for a real
     * gateway, "sandbox" for the mock. Never used in money paths.
     */
    public function mode(): string;

    /**
     * Start a payment for an order. Returns the URL the student must be
     * redirected to, plus the provider-side reference.
     *
     * @return array{redirect_url: string, provider_ref: string, channel: string}
     */
    public function initiatePayment(
        string $orderRef,
        int $amountPesewas,
        string $currency,
        string $customerEmail,
        string $callbackUrl,
        string $origin,
    ): array;

    /**
     * Verified snapshot from the gateway's own API. Never trusts the
     * browser: this is the server-to-server check.
     *
     * `pending` means the gateway has the transaction but has not settled
     * it yet (mobile money in particular): NOT a failure, and the caller
     * must not mark the payment failed or hand over content. `successful`
     * and `pending` are never both true.
     *
     * @return array{successful: bool, pending: bool, provider_ref: string, channel: string, amount_pesewas: int, currency: string, failure_reason?: string}
     */
    public function verifyPayment(string $orderRef): array;

    /** The request header this provider signs webhook deliveries with. */
    public function signatureHeader(): string;

    /** Timing-safe verification of the provider's signature over the RAW body. */
    public function verifySignature(string $rawBody, string $signature): bool;

    /**
     * Deterministic webhook event id (the dedupe key's provider part).
     * Receives the full decoded body; each provider digs where its
     * payload keeps the identity.
     *
     * @param array<string, mixed> $body
     */
    public function eventId(string $event, array $body): string;

    /** The order reference inside this provider's webhook body. */
    public function referenceFrom(array $body): ?string;

    /** True when a webhook event should trigger fulfillment. */
    public function isFulfillableEvent(string $event): bool;

    /** True for events that report a refund's progress (refund.processed …). */
    public function isRefundEvent(string $event): bool;

    /**
     * Normalise what a refund delivery claims, before anything is
     * trusted: the gateway's refund id (the idempotency key) plus
     * whatever it says about the amount and the sale it belongs to.
     * Null when the delivery carries no refund id at all.
     *
     * @param array<string, mixed> $body
     *
     * @return array{refund_id: string, status: ?string, amount_pesewas: ?int, currency: ?string, reference: ?string, transaction_id: ?string, merchant_note: ?string} | null
     */
    public function refundFrom(array $body): ?array;

    /**
     * Authoritative refund snapshot, straight from the gateway's API —
     * the same "never act on the payload alone" rule the charge path
     * follows. Status is normalised to PENDING|PROCESSING|PROCESSED|
     * FAILED, and `reference`/`transaction_id` identify the sale so the
     * order can be found even when the webhook only sent a bare id.
     *
     * @return array{status: string, amount_pesewas: int, currency: ?string, reference: ?string, transaction_id: ?string, merchant_note: ?string}
     */
    public function verifyRefund(string $refundId): array;
}
