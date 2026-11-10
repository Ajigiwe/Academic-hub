<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Format;
use App\Support\NotificationCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceiptEmail extends Mailable
{
    use Queueable, SerializesModels;

    private const CHANNELS = [
        'MOBILE_MONEY' => 'Mobile money',
        'CARD' => 'Card',
        'MOCK' => 'Sandbox (test checkout)',
    ];

    public function __construct(
        public readonly Order $order,
        public readonly int $unlockedCount = 0,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Academic Hub');

        return new Envelope(
            subject: "Payment Receipt — Order {$this->order->reference} · {$appName}",
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'items.bundle.course', 'payments']);

        $user = $this->order->user;
        $studentName = trim((string) ($user?->first_name ?: $user?->name ?: 'Student'));

        $payment = $this->order->payments->firstWhere('status', 'SUCCESSFUL')
            ?? $this->order->payments->first();
        $channel = $payment?->channel;
        $paymentMethod = $channel ? (self::CHANNELS[$channel] ?? $channel) : 'Online payment';

        $paidAt = ($this->order->paid_at ?? $this->order->created_at)?->format('j M Y, H:i') ?? '—';

        $items = $this->order->items->map(function ($item) {
            $bundle = $item->bundle;
            $code = trim((string) ($bundle?->course?->code ?? ''));
            $metaParts = array_filter([
                $code !== '' ? $code : null,
                $bundle?->level ? 'Level '.$bundle->level : null,
                $bundle?->academic_year ?: null,
            ]);

            $qty = max(1, (int) ($item->quantity ?? 1));
            $linePesewas = (int) $item->unit_price_pesewas * $qty;

            return [
                'title' => $bundle?->title ?? 'Past-question bundle',
                'meta' => implode(' · ', $metaParts),
                'price' => Format::money($linePesewas),
            ];
        })->all();

        $unlocked = $this->unlockedCount > 0
            ? $this->unlockedCount
            : $this->order->entitlements()->where('status', 'active')->count();

        return new Content(
            view: 'emails.order-receipt',
            with: [
                'studentName' => $studentName,
                'reference' => $this->order->reference,
                'paidAt' => $paidAt,
                'paymentMethod' => $paymentMethod,
                'totalFormatted' => Format::money((int) $this->order->amount_pesewas),
                'items' => $items,
                'unlockedCount' => $unlocked,
                'libraryUrl' => url('/library'),
                'receiptUrl' => url(NotificationCopy::receiptPath($this->order->reference)),
            ],
        );
    }
}

