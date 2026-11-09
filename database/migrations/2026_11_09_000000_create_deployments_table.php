<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('pending'); // pending, running, success, failed
            $table->string('trigger', 20)->default('manual');  // manual, webhook, cli
            $table->string('initiated_by')->nullable();        // admin email, github user, cli
            $table->string('commit_before', 40)->nullable();
            $table->string('commit_after', 40)->nullable();
            $table->text('commit_message')->nullable();
            $table->string('branch', 100)->default('main');
            $table->decimal('duration_seconds', 8, 2)->nullable();
            $table->longText('output')->nullable();
            $table->longText('error_output')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};

