<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Resource;
use App\Services\Payments\PaymentManager;
use App\Support\AppOrigin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // no ambiguous chars

    public function store(Request $request, PaymentManager $providers)
    {
        $data = $request->validate([
            'bundle_id' => ['required', 'integer', 'exists:bundles,id'],
        ]);

        $user = $request->user();
        $bundle = Bundle::with(['resources' => fn ($q) => $q->published()])
            ->find($data['bundle_id']);

        if (! $bundle || $bundle->status !== 'PUBLISHED') {
            return back()->withErrors(['bundle' => 'This bundle is not available for purchase.']);
        }

        // Block re-purchase only when the student already owns every paper
        // in the bundle — a partial owner pays once and unlocks the rest.
        if ($bundle->resources->isNotEmpty()) {
            $owned = Entitlement::where('user_id', $user->id)
                ->whereIn('resource_id', $bundle->resources->pluck('id'))
                ->where('status', 'active')
                ->count();
            if ($owned === $bundle->resources->count()) {
                return back()->withErrors([
                    'bundle' => 'You already own every paper in this bundle — see My Library.',
                ]);
            }
        }

        // Platform price (Admin → Settings), not the bundle's mirror row.
        $amountPesewas = $bundle->pricePesewas();

        // Fail fast on invalid email (a real gateway rejects bad addresses).
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['bundle' => 'Your email address is invalid. Please update your profile and try again.']);
        }

        [$order, $error] = $this->createOrderWithReference($user->id, $bundle, $amountPesewas);
        if ($error) {
            return back()->withErrors(['bundle' => 'Could not allocate an order reference. Please retry.']);
        }

        // Initiate payment through the abstraction layer. The return origin
        // must be the host the student is on NOW: the session cookie is
        // host-scoped, so sending them back to a different hostname logs
        // them out of their own payment (see AppOrigin). The reference
        // rides in the PATH — Paystack appends its own query parameters to
        // whatever callback URL it is given.
        $appUrl = AppOrigin::for($request);
        $callbackUrl = $appUrl.'/payments/verify/'.rawurlencode($order->reference);

        try {
            // Resolution can throw when no provider can take money (a
            // misconfigured live gateway) — that is a clear, user-facing
            // failure, never a 500 and never a free unlock.
            $provider = $providers->provider();

            $initiated = $provider->initiatePayment(
                $order->reference,
                $order->amount_pesewas,
                $order->currency,
                $user->email,
                $callbackUrl,
                $appUrl,
            );

            Payment::create([
                'order_id' => $order->id,
                'provider' => $provider->name(),
                'provider_ref' => $initiated['provider_ref'],
                'reference' => $order->reference,
                'channel' => $initiated['channel'],
                'amount_pesewas' => $order->amount_pesewas,
                'currency' => $order->currency,
            ]);

            return redirect()->away($initiated['redirect_url']);
        } catch (\Throwable $e) {
            report($e);
            $order->update(['status' => 'CANCELLED']);

            // Say what actually went wrong when we can tell. The gateway
            // rejecting the account email (Paystack refuses reserved and
            // fake domains in live mode, e.g. `…@…test`) is the one the
            // student can fix themselves — a generic "try again" would
            // have them retrying forever.
            $reason = strtolower($e->getMessage());
            $message = match (true) {
                str_contains($reason, 'email') =>
                    'The payment provider rejected your account email address. Update it in your profile to a real, working inbox and try again.',
                str_contains($reason, 'paystack_secret_key') =>
                    'Card and mobile-money payments are not available right now. Please try again later.',
                default => 'Could not start the payment. Please try again.',
            };

            return back()->withErrors(['bundle' => $message]);
        }
    }

    /**
     * Unique-reference order creation with retry loop (mirrors the
     * Next.js implementation).
     *
     * @return array{0: Order|null, 1: bool}
     */
    private function createOrderWithReference(int $userId, Bundle $bundle, int $amountPesewas): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $order = DB::transaction(function () use ($userId, $bundle, $amountPesewas) {
                    $order = Order::create([
                        'reference' => $this->generateReference(),
                        'user_id' => $userId,
                        'status' => 'PENDING',
                        'amount_pesewas' => $amountPesewas,
                        'currency' => 'GHS',
                    ]);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'bundle_id' => $bundle->id,
                        // Snapshot: past sales keep the price they were sold at.
                        'unit_price_pesewas' => $amountPesewas,
                    ]);
                    return $order;
                });

                return [$order, false];
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                continue; // reference clash — retry with a new one
            }
        }

        return [null, true];
    }

    private function generateReference(): string
    {
        $ref = 'PQ-';
        for ($i = 0; $i < 6; $i++) {
            $ref .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $ref;
    }
}
