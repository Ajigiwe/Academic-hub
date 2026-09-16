<?php

namespace App\Support;

/**
 * The words a student reads in the bell.
 *
 * A confirmation should say what was bought — in the student's language,
 * from our own records — and link to the one page that shows both the
 * money and the papers. Echoing an order reference and leaving the row
 * unclickable makes the student go looking for what we already know.
 *
 * Kept pure (no models, no queries) so the backfill migration can build
 * identical copy from raw rows.
 */
class NotificationCopy
{
    /**
     * "ACC 102 — Introduction to Financial Accounting · 2024/2025", or a
     * short list when one order holds more than one bundle.
     *
     * @param  array<int, ?string>  $titles
     */
    public static function itemsLabel(array $titles): string
    {
        $titles = array_values(array_filter(array_map(
            fn ($title) => trim((string) $title),
            $titles,
        )));

        return match (count($titles)) {
            0 => 'Your papers',
            1 => $titles[0],
            2 => $titles[0].' and '.$titles[1],
            default => $titles[0].', '.$titles[1].' and '.(count($titles) - 2).' more',
        };
    }

    /**
     * The purchase confirmation.
     *
     * @param  array<int, ?string>  $titles
     */
    public static function purchase(array $titles, int $amountPesewas, string $reference): string
    {
        $named = array_filter(array_map(fn ($title) => trim((string) $title), $titles));
        $verb = count($named) === 1 ? 'is' : 'are';

        return self::itemsLabel($titles).' '.$verb.' now in My Library. '
            .Format::money($amountPesewas).' paid for order '.$reference.'.';
    }

    /**
     * The receipt for an order — where the amount, the method, the date and
     * the papers unlocked are all on one page. Stored as a relative path so
     * it can never point a student off-site.
     */
    public static function receiptPath(string $reference, bool $refunded = false): string
    {
        return '/payment/result?status='.($refunded ? 'refunded' : 'success')
            .'&ref='.urlencode($reference);
    }
}
