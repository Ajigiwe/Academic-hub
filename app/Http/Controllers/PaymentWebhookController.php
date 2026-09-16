<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentPendingException;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\RefundProcessor;
use Illuminate\Http\Request;

/**
 * Payment webhook endpoint (mirrors /api/payments/webhook in the
 * Next.js app) — now provider-aware.
 *
 * The delivering provider is identified by WHICH signature header is
 * present on the delivery: every provider owns a unique scheme header
 * (mock → x-pastq-signature, paystack → x-paystack-signature). That
 * provider's verifier then checks its signature over the RAW body —
 * HMAC-SHA256 for the mock, HMAC-SHA512 for Paystack — with the
 * Next.js ordering preserved: 401 before any JSON parsing.
 *
 * All providers share the same chain after verification: events are
 * persisted BEFORE fulfillment so retries are idempotent and auditable.
 * The unique event_id insert (insertOrIgnore → ON CONFLICT DO NOTHING)
 * is the concurrency arbiter — a duplicate delivery loses the insert
 * race and is ACKed as deduplicated without reprocessing. Processing
 * failures leave processed_at null so the provider can retry.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentManager $providers,
        PaymentProcessor $processor,
        RefundProcessor $refunds,
    ) {
        $provider = $this->resolvingProvider($request, $providers);
        if (! $provider) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $rawBody = $request->getContent();
        $signature = (string) $request->header($provider->signatureHeader(), '');

        if (! $provider->verifySignature($rawBody, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $body = json_decode($rawBody, true);
        if (! is_array($body)) {
            return response()->json(['error' => 'Invalid JSON.'], 400);
        }

        $event = $body['event'] ?? null;
        if (! is_string($event) || $event === '') {
            return response()->json(['error' => 'Missing event/reference.'], 400);
        }

        // A refund delivery may carry only a bare transaction id, so the
        // reference is allowed to be unknown here — the RefundProcessor
        // resolves the sale from the gateway's own API instead.
        $reference = $provider->referenceFrom($body);
        if (! $reference && ! $provider->isRefundEvent($event)) {
            return response()->json(['error' => 'Missing event/reference.'], 400);
        }

        $eventId = $provider->eventId($event, $body);

        // Insert-first dedupe: the unique event_id is the arbiter,
        // enforced atomically via ON CONFLICT DO NOTHING (insertOrIgnore).
        // (Catching a unique-violation would abort the surrounding SQL
        // transaction on Postgres — ON CONFLICT avoids that entirely.)
        $inserted = WebhookEvent::insertOrIgnore([
            'provider' => $provider->name(),
            'event_id' => $eventId,
            'type' => $event,
            'reference' => $reference,
            'payload' => json_encode($body),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;

        if (! $inserted) {
            return response()->json(['ok' => true, 'deduplicated' => true]);
        }

        if ($provider->isRefundEvent($event)) {
            try {
                // Verify-then-apply, with the SAME provider that delivered
                // the event. Only a PROCESSED refund moves money state.
                $result = $refunds->process($provider, $body);
                WebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);

                return response()->json(array_filter([
                    'ok' => true,
                    'refund' => $result['status'],
                    'order' => $result['order_reference'],
                    'revoked' => $result['revoked'],
                    'unmatched' => $result['unmatched'] ?: null,
                ], fn ($value) => $value !== null));
            } catch (\Throwable $e) {
                report($e);
                // processed_at stays null → the gateway retries.
                return response()->json(['error' => 'Refund processing failed; will retry.'], 500);
            }
        }

        if (! $provider->isFulfillableEvent($event)) {
            WebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);

            return response()->json(['ok' => true, 'ignored' => true]);
        }

        try {
            // Verify-then-fulfill with the SAME provider that delivered
            // the webhook — never a different gateway than the caller.
            $processor->processSuccessfulPayment($reference, $provider->name());
            WebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);

            return response()->json(['ok' => true]);
        } catch (PaymentPendingException $e) {
            // The gateway told us a charge succeeded but its own verify
            // endpoint still reports the transaction in flight. Leave the
            // event unprocessed so a retry can settle it; never fulfill
            // on money that has not moved.
            report($e);

            return response()->json(['error' => 'Awaiting gateway confirmation; will retry.'], 500);
        } catch (\Throwable $e) {
            report($e);
            // Keep processed_at null so the provider can retry.
            return response()->json(['error' => 'Processing failed; will retry.'], 500);
        }
    }

    /**
     * Identify the delivering provider by its signature header. Returns
     * null (→ 401) when no registered provider's header is present.
     */
    private function resolvingProvider(Request $request, PaymentManager $providers): ?PaymentProvider
    {
        foreach ($providers->all() as $provider) {
            if ($request->hasHeader($provider->signatureHeader())) {
                return $provider;
            }
        }

        return null;
    }
}
