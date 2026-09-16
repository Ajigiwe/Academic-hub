<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Papers inside a bundle. Not sold individually — always reached
        // through the bundle (the unit of sale).
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('PAST_QUESTION');
            $table->string('status')->default('DRAFT');
            $table->unsignedInteger('level');
            $table->unsignedTinyInteger('semester'); // 1 | 2
            $table->string('academic_year');
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('preview_pages')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['bundle_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();   // PQ-XXXXXX
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING'); // PENDING|PAID|FAILED|CANCELLED|REFUNDED
            $table->unsignedInteger('amount_pesewas');
            $table->string('currency', 8)->default('GHS');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });

        // Price captured at purchase time; immutable if catalog changes.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bundle_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('unit_price_pesewas');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['order_id', 'bundle_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // mock | paystack | …
            $table->string('provider_ref')->nullable()->unique();
            $table->string('reference')->index();
            $table->string('channel')->default('MOCK'); // MOCK|MOBILE_MONEY|CARD
            $table->string('status')->default('PENDING'); // PENDING|SUCCESSFUL|FAILED|CANCELLED|REFUNDED
            $table->unsignedInteger('amount_pesewas');
            $table->string('currency', 8)->default('GHS');
            $table->string('failure_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Raw webhook payloads — idempotency + audit trail (spec §11).
        // eventId is the dedupe key; insert-first arbitrates concurrency.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id')->unique();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('access_type')->default('permanent');
            $table->string('status')->default('active');
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            // Prevents duplicate entitlements (spec §11) — the arbiter for
            // concurrent grant attempts.
            $table->unique(['user_id', 'resource_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('SYSTEM'); // PURCHASE|PAYMENT|SYSTEM|PROMO
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('resources');
    }
};
