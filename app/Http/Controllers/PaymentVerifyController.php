<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\PaymentPendingException;
use App\Support\AppOrigin;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\Request;

/**
 * Browser-return verification endpoint (spec §11 flow). The gateway
 * redirects the student here; we STILL verify server-side with the
 * provider before showing success. The webhook is authoritative; this
 * is a resilient fallback that also re-verifies.
 */
class PaymentVerifyController extends Controller
{
    public function __invoke(Request $request, PaymentProcessor $processor)
    {
        // Path first (our own callback), then `ref` for older links, then
        // Paystack's own `reference`/`trxref` parameters — which it appends
        // to the callback URL it was handed.
        $reference = (string) (
            $request->route('reference')
            ?: $request->query('ref')
            ?: $request->query('reference')
            ?: $request->query('trxref')
            ?: ''
        );
        // Return to the host the student is on — the one holding their
        // session cookie (see AppOrigin). Using APP_URL here is what used
        // to log a paying student out mid-checkout.
        $appUrl = AppOrigin::for($request);

        $fail = fn (string $reason) => redirect()->away(
            $appUrl.'/payment/result?status=failed&reason='.urlencode($reason)
        );

        if ($reference === '') {
            return $fail('Missing payment reference.');
        }

        $user = $request->user();
        if (! $user) {
            return redirect()->away($appUrl.'/login');
        }

        $order = Order::where('reference', $reference)->with('items.bundle')->first();
        if (! $order || $order->user_id !== $user->id) {
            return $fail('Order not found.');
        }

        // Single-bundle orders deep-link success to the bundle page.
        $bundleSlug = $order->items->count() === 1
            ? $order->items->first()->bundle->slug
            : null;
        $successUrl = $appUrl.'/payment/result?status=success&ref='.urlencode($reference)
            .($bundleSlug ? '&bundle=/bundles/'.$bundleSlug : '');

        if ($order->status === 'PAID') {
            return redirect()->away($successUrl);
        }

        // A refunded order re-opened from an old link or a stale browser
        // return must show the truth, not a fresh success page — and the
        // processor will not re-fulfil it either way.
        if (in_array($order->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            return redirect()->away(
                $appUrl.'/payment/result?status=refunded&ref='.urlencode($reference)
            );
        }

        try {
            $processor->processSuccessfulPayment($reference);

            return redirect()->away($successUrl);
        } catch (PaymentPendingException $e) {
            // Mobile money: authorised on the phone, not settled yet. Not a
            // failure — the webhook finishes the job, so reassure instead
            // of sending the student to a red error page.
            return redirect()->away(
                $appUrl.'/payment/result?status=pending&ref='.urlencode($reference)
            );
        } catch (\Throwable $e) {
            report($e);

            return $fail('We could not confirm your payment.');
        }
    }
}
