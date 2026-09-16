<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Paystack adapter — port of src/lib/paystack.ts. Ghana card and
 * mobile-money checkout. Amounts are integer pesewas on both sides of
 * the wire; verification is the server-to-server check that feeds the
 * shared PaymentProcessor (never trust the browser).
 */
class PaystackProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'paystack';
    }

    /** Ready to move real money when a secret key is present. */
    public function isConfigured(): bool
    {
        return $this->configuredKey() !== '';
    }

    /**
     * "live" for sk_live_…, "test" for sk_test_… (or any other key the
     * dashboard hands out), "unconfigured" when there is no key at all.
     */
    public function mode(): string
    {
        $key = $this->configuredKey();

        return match (true) {
            $key === '' => 'unconfigured',
            str_starts_with($key, 'sk_live_') => 'live',
            default => 'test',
        };
    }

    private function configuredKey(): string
    {
        return trim((string) config('payments.paystack.secret_key'));
    }

    private function secretKey(): string
    {
        $key = trim((string) config('payments.paystack.secret_key'));
        if ($key === '') {
            throw new \RuntimeException('Paystack is not configured: set PAYSTACK_SECRET_KEY.');
        }

        return $key;
    }

    /**
     * @return array{redirect_url: string, provider_ref: string, channel: string}
     */
    public function initiatePayment(
        string $orderRef,
        int $amountPesewas,
        string $currency,
        string $customerEmail,
        string $callbackUrl,
        string $origin,
    ): array {
        if ($amountPesewas <= 0) {
            throw new \RuntimeException('Payment amount must be a positive integer in pesewas.');
        }

        $response = $this->request('POST', '/transaction/initialize', [
            'email' => $customerEmail,
            'amount' => $amountPesewas,
            'currency' => $currency,
            'reference' => $orderRef,
            'channels' => ['mobile_money', 'card'],
            'callback_url' => $callbackUrl,
            'metadata' => ['orderRef' => $orderRef],
        ]);

        $data = $response['data'] ?? [];
        if (($response['status'] ?? false) !== true || empty($data['authorization_url'])) {
            throw new \RuntimeException(
                (string) ($response['message'] ?? 'Paystack rejected the payment.')
            );
        }

        return [
            'redirect_url' => (string) $data['authorization_url'],
            'provider_ref' => (string) ($data['reference'] ?? $orderRef),
            'channel' => 'MOBILE_MONEY',
        ];
    }

    /**
     * Transaction states Paystack reports for money that has been
     * requested but not yet settled — mobile money in particular is
     * asynchronous: the student authorises on their phone, the operator
     * confirms seconds or minutes later.
     */
    private const PENDING_STATES = ['ongoing', 'pending', 'processing', 'queued'];

    /**
     * @return array{successful: bool, pending: bool, provider_ref: string, channel: string, amount_pesewas: int, currency: string, failure_reason?: string}
     */
    public function verifyPayment(string $orderRef): array
    {
        try {
            $response = $this->request('GET', '/transaction/verify/'.rawurlencode($orderRef));
        } catch (PaystackRequestException $e) {
            // Paystack answers `400 {status:false,message:"Transaction
            // reference not found"}` for a reference this account has
            // never seen — verified against the live API. (404 is handled
            // too, in case they ever normalise it.) That is a permanent
            // answer, so it must NOT keep a webhook retrying for days;
            // anything else (401 wrong keys, 5xx, timeouts) stays an error
            // so deliveries retry and the misconfiguration is loud.
            if (in_array($e->status, [400, 404], true)) {
                return $this->unsuccessful($orderRef, 'Paystack has no record of that payment reference.');
            }

            throw $e;
        }

        $payment = $response['data'] ?? null;
        $state = is_array($payment) ? (string) ($payment['status'] ?? '') : '';

        if (($response['status'] ?? false) !== true || ! is_array($payment) || $state !== 'success') {
            $pending = in_array($state, self::PENDING_STATES, true);

            return $this->unsuccessful(
                $orderRef,
                $pending
                    ? 'Paystack is still confirming this payment.'
                    : 'Paystack did not report a successful payment.',
                $payment,
                $pending,
            );
        }

        return [
            'successful' => true,
            'pending' => false,
            'provider_ref' => (string) ($payment['reference'] ?? $orderRef),
            // The gateway's own transaction id — refund webhooks identify
            // the sale by this, not by our reference.
            'provider_txn_id' => isset($payment['id']) ? (string) $payment['id'] : null,
            'channel' => $this->channel($payment['channel'] ?? null),
            'amount_pesewas' => (int) ($payment['amount'] ?? 0),
            'currency' => (string) ($payment['currency'] ?? 'GHS'),
        ];
    }

    /**
     * A verification that did not succeed — either finally failed or
     * still in flight (pending=true).
     *
     * @param array<string, mixed>|null $payment
     *
     * @return array{successful: bool, pending: bool, provider_ref: string, channel: string, amount_pesewas: int, currency: string, failure_reason: string}
     */
    private function unsuccessful(
        string $orderRef,
        string $reason,
        ?array $payment = null,
        bool $pending = false,
    ): array {
        return [
            'successful' => false,
            'pending' => $pending,
            'provider_ref' => (string) ($payment['reference'] ?? $orderRef),
            'channel' => $this->channel($payment['channel'] ?? null),
            'amount_pesewas' => 0,
            'currency' => (string) ($payment['currency'] ?? 'GHS'),
            'failure_reason' => $reason,
        ];
    }

    /**
     * Verify Paystack's HMAC-SHA512 signature over the RAW webhook body
     * with a timing-safe comparison (port of verifyPaystackSignature).
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $key = $this->configuredKey();
        if ($key === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $key);

        return is_string($signature)
            && strlen($signature) === strlen($expected)
            && hash_equals($expected, $signature);
    }

    /**      * Deterministic event id (port of paystackEventId): the gateway's own
      * event id, falling back to the reference. Feeds the shared
      * webhook_events dedupe arbiter. Identity lives under body.data
      * (Paystack's envelope), not the body root.
      *
      * @param array<string, mixed> $body
      */
    public function eventId(string $event, array $body): string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $gatewayId = $data['id'] ?? $data['reference'] ?? 'unknown';

        return "paystack:{$event}:".(string) $gatewayId;
    }

    /** True when the webhook event should trigger fulfillment. */
    public function isFulfillableEvent(string $event): bool
    {
        return $event === 'charge.success';
    }

    // ── Refunds ──────────────────────────────────────────────────────

    /** refund.pending | refund.processing | refund.processed | refund.failed */
    public function isRefundEvent(string $event): bool
    {
        return str_starts_with($event, 'refund.');
    }

    /**
     * @param array<string, mixed> $body
     */
    public function refundFrom(array $body): ?array
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $refundId = $data['id'] ?? null;

        if (! is_string($refundId) && ! is_int($refundId)) {
            return null;
        }

        $transaction = $data['transaction'] ?? null;

        return [
            'refund_id' => (string) $refundId,
            'status' => $this->refundStatus(
                (string) ($data['status'] ?? ''),
                (string) ($body['event'] ?? ''),
            ),
            'amount_pesewas' => is_numeric($data['amount'] ?? null) ? (int) $data['amount'] : null,
            'currency' => isset($data['currency']) ? (string) $data['currency'] : null,
            'reference' => $this->referenceOf($transaction)
                ?? (isset($data['reference']) ? (string) $data['reference'] : null),
            'transaction_id' => $this->transactionIdOf($transaction),
            'merchant_note' => isset($data['merchant_note']) ? (string) $data['merchant_note'] : null,
        ];
    }

    /**
     * GET /refund/:id — the gateway's own answer, not the delivery's.
     * The refund object identifies the sale by `transaction`, which is a
     * bare id in some responses, so a missing reference is resolved with
     * GET /transaction/:id (that endpoint accepts an id or reference).
     *
     * Unlike a charge's "reference not found" (verified: HTTP 400), an
     * unknown refund id answers **404 "Refund not found"** — which is
     * deliberately left as an ERROR here rather than a clean refusal: a
     * refund can be unfetchable for a moment right after it is created,
     * and refund state must never be guessed. The webhook keeps the
     * delivery (processed_at stays null) and Paystack retries.
     */
    public function verifyRefund(string $refundId): array
    {
        $response = $this->request('GET', '/refund/'.rawurlencode($refundId));
        $refund = $response['data'] ?? null;

        if (($response['status'] ?? false) !== true || ! is_array($refund)) {
            throw new \RuntimeException(
                (string) ($response['message'] ?? 'Paystack did not return the refund.')
            );
        }

        $transaction = $refund['transaction'] ?? null;
        $reference = $this->referenceOf($transaction);
        $transactionId = $this->transactionIdOf($transaction);

        if ($reference === null && $transactionId !== null) {
            $reference = $this->transactionReference($transactionId);
        }

        return [
            'status' => $this->refundStatus((string) ($refund['status'] ?? ''), ''),
            'amount_pesewas' => (int) ($refund['amount'] ?? 0),
            'currency' => isset($refund['currency']) ? (string) $refund['currency'] : null,
            'reference' => $reference,
            'transaction_id' => $transactionId,
            'merchant_note' => isset($refund['merchant_note']) ? (string) $refund['merchant_note'] : null,
        ];
    }

    /** Normalise a gateway refund status or event name to our enum. */
    private function refundStatus(string $status, string $event): string
    {
        $value = strtolower($status !== '' ? $status : $event);

        return match (true) {
            str_contains($value, 'processed') => 'PROCESSED',
            str_contains($value, 'processing') => 'PROCESSING',
            str_contains($value, 'failed') => 'FAILED',
            default => 'PENDING',
        };
    }

    /** The sale reference inside a refund's `transaction` field. */
    private function referenceOf(mixed $transaction): ?string
    {
        if (! is_array($transaction)) {
            return null;
        }

        $reference = $transaction['reference'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /** The gateway's transaction id inside a refund's `transaction` field. */
    private function transactionIdOf(mixed $transaction): ?string
    {
        if (is_array($transaction)) {
            $id = $transaction['id'] ?? null;

            return is_string($id) || is_int($id) ? (string) $id : null;
        }

        return is_string($transaction) || is_int($transaction) ? (string) $transaction : null;
    }

    /** GET /transaction/:id — resolves a bare transaction id to a reference. */
    private function transactionReference(string $transactionId): ?string
    {
        $response = $this->request('GET', '/transaction/'.rawurlencode($transactionId));
        $transaction = $response['data'] ?? null;
        $reference = is_array($transaction) ? ($transaction['reference'] ?? null) : null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * The order reference inside a delivery. Charge payloads carry it at
     * `data.reference`; refund payloads carry the sale under
     * `data.transaction.reference` (or a bare transaction id, which is
     * resolved later from the API rather than guessed here).
     *
     * @param array<string, mixed> $body
     */
    public function referenceFrom(array $body): ?string
    {
        $reference = $body['data']['reference'] ?? null;
        if (is_string($reference) && $reference !== '') {
            return $reference;
        }

        return $this->referenceOf($body['data']['transaction'] ?? null);
    }

    /** The signature header this provider signs webhooks with. */
    public function signatureHeader(): string
    {
        return 'x-paystack-signature';
    }

    private function channel(mixed $value): string
    {
        return $value === 'card' ? 'CARD' : 'MOBILE_MONEY';
    }

    /**
     * Overridable (protected) so tests can stub the Paystack API while
     * keeping signature verification fully real.
     *
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        $http = Http::withToken($this->secretKey())
            ->acceptJson()
            ->baseUrl((string) config('payments.paystack.base_url', 'https://api.paystack.co'))
            ->timeout(15);

        $response = $method === 'POST'
            ? $http->post($path, $payload)
            : $http->get($path);

        if ($response->failed()) {
            $message = $response->json('message') ?? "HTTP {$response->status()}";
            throw new PaystackRequestException(
                (int) $response->status(),
                $path,
                "Paystack {$path} failed: {$message}",
            );
        }

        return (array) $response->json();
    }
}
