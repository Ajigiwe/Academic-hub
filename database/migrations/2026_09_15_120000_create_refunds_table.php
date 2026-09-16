<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refunds a gateway processed (Paystack drives these from its
     * dashboard or its API). One row per gateway refund so a replayed
     * webhook cannot refund twice, and so a partial refund that later
     * becomes a full one is an update rather than a second event.
     *
     * Also records the gateway's own transaction id on `payments`: a
     * refund webhook identifies the sale by THAT id (its `transaction`
     * field is often a bare number, not our reference), so keeping it
     * makes an unmatched refund impossible without a second API call.
     */
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('provider_ref'); // the gateway's refund id
            $table->string('status')->default('PENDING'); // PENDING|PROCESSING|PROCESSED|FAILED
            $table->unsignedInteger('amount_pesewas');
            $table->string('currency', 8)->default('GHS');
            $table->string('reason')->nullable(); // the gateway's merchant note
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // The gateway's refund id is the idempotency key.
            $table->unique(['provider', 'provider_ref']);
            $table->index(['order_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_txn_id')->nullable()->after('provider_ref')->index();
        });

        Schema::table('entitlements', function (Blueprint $table) {
            // When a full refund revoked this grant — kept for the audit
            // trail instead of deleting the row.
            $table->timestamp('revoked_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['provider_txn_id']);
            $table->dropColumn('provider_txn_id');
        });

        Schema::dropIfExists('refunds');
    }
};
