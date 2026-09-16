<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Setting;

/**
 * ONE price for every bundle, set in Admin → Settings.
 *
 * The store sells whole bundles (course + academic year), never single
 * papers, so a single platform price keeps the catalogue honest and
 * makes a promo a one-field change. The price lives in the `settings`
 * table under `pricing.bundle_price_pesewas` — always integer pesewas,
 * never floats.
 *
 * `bundles.price_pesewas` survives as a DENORMALISED MIRROR so the
 * catalogue can still sort by price and old rows stay readable, but it
 * is derived: every save re-syncs all bundles in one statement. The
 * money paths (checkout, every price quote) read THIS class instead of
 * the column, so a stale mirror row can never charge a price nobody
 * agreed to.
 */
class BundlePricing
{
    public const KEY = 'pricing.bundle_price_pesewas';

    /** GH₵15.00 — launch price, applied to every bundle. */
    public const DEFAULT_PESEWAS = 1500;

    public const MIN_CEDIS = 0;
    public const MAX_CEDIS = 1000;

    /** The platform price in integer pesewas. */
    public static function pesewas(): int
    {
        $value = Setting::where('key', self::KEY)->value('value');

        return is_numeric($value) ? (int) $value : self::DEFAULT_PESEWAS;
    }

    public static function cedis(): float
    {
        return static::pesewas() / 100;
    }

    /** "15.00" — display only; the currency label belongs to the view. */
    public static function formatted(): string
    {
        return number_format(static::cedis(), 2);
    }

    /**
     * Persist the platform price and re-sync every bundle's mirror row.
     *
     * Orders already placed are untouched: order_items snapshots
     * unit_price_pesewas at checkout, so past sales keep the price they
     * were sold at no matter what the platform price is today.
     */
    public static function set(int $pesewas): int
    {
        Setting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => (string) $pesewas],
        );

        Bundle::query()->update(['price_pesewas' => $pesewas]);

        return $pesewas;
    }

    /** Bundles whose mirror row is out of step with the platform price. */
    public static function outOfSyncCount(): int
    {
        return Bundle::where('price_pesewas', '!=', static::pesewas())->count();
    }
}
