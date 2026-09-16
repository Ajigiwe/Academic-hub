<?php

namespace App\Services\Payments;

/**
 * A non-2xx answer from Paystack's API, carrying the HTTP status so the
 * caller can tell a permanent answer (404 — this account has never seen
 * that reference) from a transient one (5xx, timeouts, rate limits) that
 * should be retried rather than swallowed.
 */
class PaystackRequestException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($message);
    }
}
