<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Moolre payment gateway adapter (Ghana card and mobile money).
 *
 * Moolre uses:
 * - Public API Key (JWT) for client-facing / payment initiation requests
 * - Private API Key for sensitive operations and webhook signature verification
 * - X-Moolre-Signature (HMAC-SHA256: t=<timestamp>,v1=<hash>)
 *
 * Amounts are converted from pesewas to Cedis for Moolre ("50.00"), and
 * back to integer pesewas on verification.
 */
class MoolreProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'moolre';
    }

    /**
     * Ready to move money when public key, private key, and username are set.
     */
    public function isConfigured(): bool
    {
        return $this->configuredPublicKey() !== ''
            && $this->configuredPrivateKey() !== '';
    }

    /**
     * "live" for production API, "test" for sandbox URL or test keys,
     * "unconfigured" when keys are missing.
     */
    public function mode(): string
    {
        if (! $this->isConfigured()) {
            return 'unconfigured';
        }

        $baseUrl = (string) config('payments.moolre.base_url', 'https://api.moolre.com');

        return str_contains($baseUrl, 'sandbox') ? 'test' : 'live';
    }

    public function configuredPublicKey(): string
    {
        return trim((string) config('payments.moolre.public_key'));
    }

    public function configuredPrivateKey(): string
    {
        return trim((string) config('payments.moolre.private_key'));
    }

    public function username(): string
    {
        return trim((string) config('payments.moolre.username', ''));
    }

    /**
     * Resolve the wallet account number: explicit config if set, otherwise
     * extracted from the public key JWT payload (walletid).
     */
    public function accountNumber(): string
    {
        $explicit = trim((string) config('payments.moolre.account_number'));
        if ($explicit !== '') {
            return $explicit;
        }

        $pubKey = $this->configuredPublicKey();
        if (str_contains($pubKey, '.')) {
            $parts = explode('.', $pubKey);
            if (isset($parts[1])) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (is_array($payload) && ! empty($payload['walletid'])) {
                    return (string) $payload['walletid'];
                }
            }
        }

        return '';
    }

    /**
     * Start a payment via Moolre Web POS (POST /embed/link).
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
    ): array {
        if ($amountPesewas <= 0) {
            throw new \RuntimeException('Payment amount must be a positive integer in pesewas.');
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Moolre is not configured: set MOOLRE_PUBLIC_KEY and MOOLRE_PRIVATE_KEY.');
        }

        $amountGhs = number_format($amountPesewas / 100, 2, '.', '');
        $webhookUrl = rtrim($origin, '/').'/payments/webhook';

        $payload = [
            'state' => 'starter',
            'accountnumber' => $this->accountNumber(),
            'tx_source' => 'php-sdk',
            'type' => 1,
            'amount' => $amountGhs,
            'currency' => $currency === 'GHS' ? 'GHS' : $currency,
            'email' => $customerEmail,
            'reference' => $orderRef,
            'externalref' => $orderRef,
            'reusable' => '0',
            'callback' => $webhookUrl,
            'callback_url' => $webhookUrl,
            'redirect' => $callbackUrl,
            'redirect_url' => $callbackUrl,
            'metadata' => [
                'orderRef' => $orderRef,
                'email' => $customerEmail,
            ],
        ];

        try {
            $response = $this->request('POST', '/embed/link', $payload);
        } catch (\Throwable $e) {
            $response = $this->request('POST', '/embed/src/start', $payload);
        }

        $data = $response['data'] ?? [];
        if (($response['status'] ?? 0) !== 1 || empty($data['authorization_url'])) {
            $msg = $response['message'] ?? 'Moolre rejected the payment initiation.';
            throw new \RuntimeException((string) $msg);
        }

        return [
            'redirect_url' => (string) $data['authorization_url'],
            'provider_ref' => (string) ($data['reference'] ?? $orderRef),
            'channel' => 'MOBILE_MONEY',
        ];
    }

    /**
     * Verified snapshot from Moolre's Payment Status API (POST /open/transact/status).
     *
     * @return array{successful: bool, pending: bool, provider_ref: string, provider_txn_id?: ?string, channel: string, amount_pesewas: int, currency: string, failure_reason?: string}
     */
    public function verifyPayment(string $orderRef): array
    {
        $payload = [
            'type' => 1,
            'idtype' => 1, // 1 = Unique externalref
            'id' => $orderRef,
            'accountnumber' => $this->accountNumber(),
        ];

        try {
            $response = $this->request('POST', '/open/transact/status', $payload);
        } catch (MoolreRequestException $e) {
            if (in_array($e->status, [400, 404], true)) {
                return $this->unsuccessful($orderRef, 'Moolre has no record of that payment reference.');
            }

            throw $e;
        }

        $data = $response['data'] ?? null;
        $status = (int) ($response['status'] ?? 0);
        $code = (string) ($response['code'] ?? '');
        $txstatus = is_array($data) ? (int) ($data['txstatus'] ?? -1) : -1;

        // Moolre reports code "SS01" or txstatus = 1 for successful transactions
        if ($status === 1 && $txstatus === 1) {
            $amountPesewas = isset($data['amount'])
                ? (int) round(((float) $data['amount']) * 100)
                : 0;

            return [
                'successful' => true,
                'pending' => false,
                'provider_ref' => (string) ($data['externalref'] ?? $orderRef),
                'provider_txn_id' => isset($data['transactionid']) ? (string) $data['transactionid'] : null,
                'channel' => 'MOBILE_MONEY',
                'amount_pesewas' => $amountPesewas,
                'currency' => (string) ($data['currency'] ?? 'GHS'),
            ];
        }

        $isPending = ($txstatus === 0 || in_array($code, ['P00', 'PEN01', 'TX01'], true));

        return $this->unsuccessful(
            $orderRef,
            $isPending
                ? 'Moolre is still confirming this payment.'
                : (string) ($response['message'] ?? 'Moolre did not report a successful payment.'),
            $data,
            $isPending,
        );
    }

    private function unsuccessful(
        string $orderRef,
        string $reason,
        ?array $data = null,
        bool $pending = false,
    ): array {
        return [
            'successful' => false,
            'pending' => $pending,
            'provider_ref' => (string) ($data['externalref'] ?? $orderRef),
            'channel' => 'MOBILE_MONEY',
            'amount_pesewas' => 0,
            'currency' => (string) ($data['currency'] ?? 'GHS'),
            'failure_reason' => $reason,
        ];
    }

    /** The signature header Moolre sends with webhook notifications. */
    public function signatureHeader(): string
    {
        return 'x-moolre-signature';
    }

    /**
     * Verify Moolre's HMAC-SHA256 signature over the raw webhook body.
     * Header format: t=<unix_timestamp>,v1=<hash>
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $webhookSecret = (string) config('payments.webhook_secret');
        $privateKey = $this->configuredPrivateKey();
        $keys = array_values(array_filter([$webhookSecret, $privateKey]));

        if (empty($keys) || $signature === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $segment) {
            if (str_contains($segment, '=')) {
                [$k, $v] = explode('=', trim($segment), 2);
                $parts[trim($k)] = trim($v);
            }
        }

        $timestamp = $parts['t'] ?? null;
        $hash = $parts['v1'] ?? $signature;

        if ($timestamp !== null && is_numeric($timestamp)) {
            // Reject deliveries older than 5 minutes to prevent replay attacks
            if (abs(time() - (int) $timestamp) > 300) {
                return false;
            }
        }

        foreach ($keys as $k) {
            $expected = hash_hmac('sha256', $rawBody, $k);
            if (hash_equals($expected, $hash)) {
                return true;
            }
            if ($timestamp !== null) {
                $expectedWithTime = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $k);
                if (hash_equals($expectedWithTime, $hash)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Deterministic webhook event id (the dedupe key's provider part).
     */
    public function eventId(string $event, array $body): string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $gatewayId = $data['transactionid'] ?? $data['externalref'] ?? $body['event_id'] ?? 'unknown';

        return "moolre:{$event}:".(string) $gatewayId;
    }

    /** The order reference inside Moolre's webhook payload. */
    public function referenceFrom(array $body): ?string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $ref = $data['externalref'] ?? $data['reference'] ?? null;

        if (is_string($ref) && $ref !== '') {
            return $ref;
        }

        return null;
    }

    /** True when a webhook event represents a successful charge. */
    public function isFulfillableEvent(string $event): bool
    {
        return in_array($event, ['P01', 'payment.succeeded', 'charge.success', 'payment.success'], true);
    }

    public function isRefundEvent(string $event): bool
    {
        return str_starts_with($event, 'refund.');
    }

    public function refundFrom(array $body): ?array
    {
        return null;
    }

    public function verifyRefund(string $refundId): array
    {
        throw new \RuntimeException('Moolre does not support programmatic refund verification.');
    }

    /**
     * HTTP call to Moolre's API. Protected so tests can stub responses.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $payload = []): array
    {
        $baseUrl = rtrim((string) config('payments.moolre.base_url', 'https://api.moolre.com'), '/');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-USER' => $this->username(),
            'X-API-PUBKEY' => $this->configuredPublicKey(),
        ];

        // Only include private key for endpoints that require it (e.g. transfers/payouts/account status)
        if (str_starts_with($path, '/open/account/status') || str_starts_with($path, '/open/transfer/')) {
            $privateKey = $this->configuredPrivateKey();
            if ($privateKey !== '') {
                $headers['X-API-KEY'] = $privateKey;
            }
        }

        $http = Http::withHeaders($headers)
            ->baseUrl($baseUrl)
            ->timeout(15);

        $response = $method === 'POST'
            ? $http->post($path, $payload)
            : $http->get($path, $payload);

        if ($response->failed()) {
            $msg = $response->json('message') ?? "HTTP {$response->status()}";
            throw new MoolreRequestException(
                (int) $response->status(),
                $path,
                "Moolre {$path} failed: {$msg}",
            );
        }

        return (array) $response->json();
    }
}
