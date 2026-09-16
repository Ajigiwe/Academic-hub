<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Format;
use Illuminate\Http\Request;

/**
 * The page a student lands on after paying — the receipt for a purchase,
 * a "still confirming" notice, a refund notice, or a clear statement that
 * nothing was charged.
 *
 * The gateway only ever hands us a reference, so the order is looked up
 * here and its details are shown ONLY to the student who owns it (the
 * link is public and carries no session guarantee). Guests — or anyone
 * with someone else's reference — get the same page without the receipt.
 */
class PaymentResultController extends Controller
{
    /** Gateway channels in student language. */
    private const CHANNELS = [
        'MOBILE_MONEY' => 'Mobile money',
        'CARD' => 'Card',
        'MOCK' => 'Sandbox (no real charge)',
    ];

    /** Headline copy per state. */
    private const STATES = [
        'success' => [
            'title' => 'Payment confirmed',
            'lead' => 'Your payment went through and the papers are now in your library.',
            'badge' => 'badge-success',
            'label' => 'Paid',
        ],
        'pending' => [
            'title' => 'Payment being confirmed',
            'lead' => 'Your bank or mobile-money provider is still confirming this payment. Nothing more is needed from you, and it is safe to close this page.',
            'badge' => 'badge-gold',
            'label' => 'Awaiting confirmation',
        ],
        'refunded' => [
            'title' => 'This order was refunded',
            'lead' => 'The money for this order has been returned to your original payment method.',
            'badge' => 'badge-neutral',
            'label' => 'Refunded',
        ],
        'cancelled' => [
            'title' => 'Payment cancelled',
            'lead' => 'You stopped before paying, so nothing was charged.',
            'badge' => 'badge-neutral',
            'label' => 'Not charged',
        ],
        'failed' => [
            'title' => 'Payment not confirmed',
            'lead' => 'We could not confirm this payment.',
            'badge' => 'badge-danger',
            'label' => 'Not confirmed',
        ],
    ];

    public function __invoke(Request $request)
    {
        $status = array_key_exists((string) $request->query('status'), self::STATES)
            ? (string) $request->query('status')
            : 'failed';

        $reference = trim((string) $request->query('ref', ''));
        $reason = trim((string) $request->query('reason', ''));
        $order = $this->orderFor($request, $reference);

        return view('payment.result', [
            'status' => $status,
            'ref' => $reference,
            'reason' => $status === 'failed' ? $reason : '',
            'bundleHref' => $this->bundleHref($request->query('bundle')),
            'state' => self::STATES[$status],
            'receipt' => $order ? $this->receipt($order, $status) : null,
            'receiptHint' => $order ? null : $this->receiptHint($request, $reference),
        ]);
    }

    /**
     * Why there is no receipt — a reference the viewer can't see the details
     * of. The commonest cause is exactly what students hit: the gateway
     * returned them to a host without their session, so they read "Payment
     * confirmed" with no receipt and no idea what was bought.
     *
     * @return array{text: string, href: ?string, label: ?string}|null
     */
    private function receiptHint(Request $request, string $reference): ?array
    {
        if ($reference === '') {
            return null;
        }

        if ($request->user()) {
            return [
                'text' => 'This order is not on your account. If you paid with a different email, sign in with that account to see its receipt.',
                'href' => null,
                'label' => null,
            ];
        }

        return [
            'text' => 'Sign in with the account you paid with to see the receipt and open what you bought.',
            'href' => route('login'),
            'label' => 'Sign in',
        ];
    }

    /** The order behind a reference — only when the viewer owns it. */
    private function orderFor(Request $request, string $reference): ?Order
    {
        $user = $request->user();
        if ($reference === '' || ! $user) {
            return null;
        }

        $order = Order::where('reference', $reference)
            ->with(['items.bundle.course', 'payments', 'refunds'])
            ->first();

        return $order && (int) $order->user_id === (int) $user->id ? $order : null;
    }

    /**
     * The receipt rows. Everything here is derived from OUR records, never
     * from the query string, so the amount a student reads is the amount
     * we charged.
     *
     * @return array{
     *     reference: string,
     *     items: list<array{code: string, title: string, meta: string}>,
     *     rows: list<array{label: string, value: string, note: ?string}>,
     *     access: ?string
     * }
     */
    private function receipt(Order $order, string $status): array
    {
        $payment = $order->payments->first();
        $refunded = (int) $order->refunds->where('status', 'PROCESSED')->sum('amount_pesewas');
        $paidAt = $order->paid_at ?? $order->created_at;

        $rows = [];

        if ($status === 'refunded') {
            $rows[] = [
                'label' => 'Refunded',
                'value' => Format::money($refunded ?: $order->amount_pesewas),
                'note' => $refunded > 0 && $refunded < $order->amount_pesewas
                    ? 'of '.Format::money($order->amount_pesewas).' paid'
                    : null,
            ];
        } elseif ($status === 'cancelled' || $status === 'failed') {
            $rows[] = ['label' => 'Amount', 'value' => Format::money($order->amount_pesewas), 'note' => 'no charge was made'];
        } else {
            $rows[] = ['label' => 'Amount', 'value' => Format::money($order->amount_pesewas), 'note' => null];
        }

        if ($payment?->channel && $status !== 'cancelled' && $status !== 'failed') {
            $rows[] = ['label' => 'Method', 'value' => self::CHANNELS[$payment->channel] ?? $payment->channel, 'note' => null];
        }

        if ($status === 'success' || $status === 'refunded') {
            $rows[] = ['label' => 'Date', 'value' => $paidAt?->format('j M Y, H:i') ?? '—', 'note' => null];
        }

        return [
            'reference' => $order->reference,
            'items' => $order->items->map(function ($item) {
                $bundle = $item->bundle;

                return [
                    'code' => $bundle?->course?->code ?? 'Bundle',
                    'title' => $bundle?->title ?? 'Past-question bundle',
                    'meta' => $bundle
                        ? 'Level '.$bundle->level.' · '.$bundle->academic_year
                        : '',
                ];
            })->all(),
            'rows' => $rows,
            'access' => $this->accessLine($order, $status),
        ];
    }

    /** What the student can actually read right now, in one line. */
    private function accessLine(Order $order, string $status): ?string
    {
        $unlocked = $order->entitlements()->where('status', 'active')->count();

        return match ($status) {
            'success' => $unlocked > 0
                ? $unlocked.' '.($unlocked === 1 ? 'paper' : 'papers').' unlocked — open My Library to read'
                : 'Your papers appear in My Library as soon as the gateway confirms them',
            'pending' => 'Unlocks automatically once the payment clears',
            'refunded' => $unlocked > 0
                ? 'Access kept — these papers are still yours'
                : 'Access removed by the refund',
            default => null,
        };
    }

    /** A relative bundle path from the gateway round-trip, if it is safe. */
    private function bundleHref(mixed $bundle): ?string
    {
        return is_string($bundle) && str_starts_with($bundle, '/') && ! str_starts_with($bundle, '//')
            ? $bundle
            : null;
    }
}
