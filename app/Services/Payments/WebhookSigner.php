<?php

namespace App\Services\Payments;

/**
 * HMAC-SHA256 signing for the mock gateway's webhooks. The mock gateway
 * signs the RAW body before any JSON parsing; verification lives on the
 * provider itself (MockProvider::verifySignature — same algorithm, same
 * secret), so the two sides must stay in sync.
 *
 * Paystack needs no signer here: it signs its own deliveries with
 * HMAC-SHA512 over the raw body, verified by PaystackProvider.
 */
class WebhookSigner
{
    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) config('payments.webhook_secret'));
    }

    public function verify(string $rawBody, string $signature): bool
    {
        $secret = (string) config('payments.webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($rawBody), $signature);
    }
}
