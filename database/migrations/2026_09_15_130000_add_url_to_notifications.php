<?php

use App\Support\NotificationCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications used to be dead text: a purchase confirmation echoed the
 * order reference and nothing was clickable. Add a destination, and
 * backfill the rows already in the table so a student's existing bell is
 * as useful as a new one.
 *
 * The backfill is deliberately self-contained (raw queries, explicit copy
 * via NotificationCopy) — a migration must keep working after the models
 * around it move on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('url')->nullable();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }

    /** Point existing rows at the right page, and say what was bought. */
    private function backfill(): void
    {
        DB::table('notifications')
            ->whereNull('url')
            ->orderBy('id')
            ->chunkById(200, function ($notifications) {
                foreach ($notifications as $notification) {
                    if (! preg_match('/PQ-[A-Z0-9]+/', (string) $notification->body, $matches)) {
                        continue;
                    }

                    $order = DB::table('orders')->where('reference', $matches[0])->first();
                    if (! $order) {
                        continue;
                    }

                    $refunded = in_array($order->status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true);

                    $update = ['url' => NotificationCopy::receiptPath($order->reference, $refunded)];

                    // Rewrite only a purchase that still stands — a refund
                    // notice already names the amount and the outcome.
                    if ($notification->type === 'PURCHASE' && $order->status === 'PAID') {
                        $titles = DB::table('order_items')
                            ->join('bundles', 'bundles.id', '=', 'order_items.bundle_id')
                            ->where('order_items.order_id', $order->id)
                            ->pluck('bundles.title')
                            ->all();

                        $update['body'] = NotificationCopy::purchase(
                            $titles,
                            (int) $order->amount_pesewas,
                            $order->reference,
                        );
                    }

                    DB::table('notifications')->where('id', $notification->id)->update($update);
                }
            });
    }
};
