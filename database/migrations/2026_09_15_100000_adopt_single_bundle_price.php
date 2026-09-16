<?php

use App\Services\BundlePricing;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adopt the single platform bundle price.
 *
 * Before this, every bundle carried its own `price_pesewas`. The store
 * now sells all bundles at one price (Admin → Settings), so the setting
 * row is created here and every bundle's mirror row is aligned to it.
 *
 * Which price wins? The MOST COMMON price in the catalogue, because
 * that is what most customers already pay and a re-price should move as
 * few bundles as possible. Free rows (0) and empty catalogues fall back
 * to the launch default — never adopt a zero, or one promo bundle would
 * silently make the whole store free.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('bundles')
            ->where('price_pesewas', '>', 0)
            ->groupBy('price_pesewas')
            ->orderByRaw('COUNT(*) DESC, price_pesewas ASC')
            ->value('price_pesewas');

        $pesewas = $existing === null ? BundlePricing::DEFAULT_PESEWAS : (int) $existing;

        DB::table('settings')->updateOrInsert(
            ['key' => BundlePricing::KEY],
            ['value' => (string) $pesewas, 'updated_at' => now()],
        );

        DB::table('bundles')->update(['price_pesewas' => $pesewas]);
    }

    /**
     * Irreversible on purpose: the per-bundle prices this migration
     * collapsed are gone, and there is nothing sensible to roll back to.
     * Dropping the setting row is enough to fall back to the default.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', BundlePricing::KEY)->delete();
    }
};
