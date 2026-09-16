<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Support\NotificationCopy;

/**
 * Port of the notification creation in the Next.js fulfillOrder
 * (src/lib/payments.ts) plus a small surface for the bell + list page.
 * The Next.js app creates notifications on purchase but never displays
 * them — here the student actually gets the bell and the list.
 *
 * Every notification carries a destination: a record that says "Purchase
 * confirmed" and names what was bought is only half useful if it cannot
 * be clicked through to it.
 */
class Notifications
{
    /** A purchase confirmation naming the papers and linking to its receipt. */
    public static function purchase(Order $order): Notification
    {
        $titles = $order->items->map(fn ($item) => $item->bundle?->title)->all();

        return static::create(
            $order->user_id,
            'PURCHASE',
            'Purchase confirmed',
            NotificationCopy::purchase($titles, $order->amount_pesewas, $order->reference),
            NotificationCopy::receiptPath($order->reference),
        );
    }

    public static function create(
        string $userId,
        string $type,
        string $title,
        string $body,
        ?string $url = null,
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
    }
}
