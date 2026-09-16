<?php

namespace App\Services\Payments;

/**
 * The gateway has the transaction but has not settled it yet — normal
 * for mobile money, where the student authorises on their phone and the
 * operator confirms shortly after. The payment is NOT failed and no
 * content may be released; the webhook (or a later callback) finishes
 * the job once money actually moves.
 */
class PaymentPendingException extends \RuntimeException
{
    public function __construct(string $orderRef, string $reason)
    {
        parent::__construct("Payment for {$orderRef} is still pending: {$reason}");
    }
}
