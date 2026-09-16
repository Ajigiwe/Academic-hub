<?php

namespace App\Support;

/**
 * Display helpers — ports of the Next.js src/lib/resources.ts
 * formatBytes/formatPrice utilities used by the cards.
 */
class Format
{
    /** "812 KB" / "1.2 MB" — matches formatBytes (KB until 1 MB, then MB). */
    public static function bytes(?int $bytes): string
    {
        if (! $bytes) {
            return '';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }

    /**
     * "15.00" from integer pesewas — money is never stored as a float,
     * so it is never formatted from one either.
     */
    public static function cedis(int $pesewas): string
    {
        return number_format($pesewas / 100, 2);
    }

    /** "GH₵15.00" — the amount as students see it. */
    public static function money(int $pesewas): string
    {
        return 'GH₵'.self::cedis($pesewas);
    }
}
