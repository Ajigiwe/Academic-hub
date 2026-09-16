<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The actual document behind a paper (PDF in private storage).
        // Original filenames never appear in storage paths (spec §32).
        Schema::create('resource_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key')->unique(); // private/resources/8f/4c/93b1c2e8.bin
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum')->nullable(); // sha256 integrity
            $table->string('original_name');        // audit only
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['resource_id', 'is_current']);
        });

        // Audit trail of who opened what (spec §26/§27).
        Schema::create('viewing_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            // Null for role-granted access (admins).
            $table->foreignId('entitlement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resource_id']);
        });

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // OPENED_RESOURCE|STARTED_VIEWER|PAGE_VIEWED|ACCESS_DENIED
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('detail')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['resource_id', 'created_at']);
        });

        // "Recently viewed" (spec §12) — one row per (user, resource).
        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->primary(['user_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
        Schema::dropIfExists('access_logs');
        Schema::dropIfExists('viewing_sessions');
        Schema::dropIfExists('resource_files');
    }
};
