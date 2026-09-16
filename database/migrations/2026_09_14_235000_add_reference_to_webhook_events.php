<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mock provider previously queried the JSON payload
     * ("payload->>'reference'") — Postgres syntax that breaks on
     * MySQL/MariaDB. A dedicated indexed column is DB-agnostic,
     * faster, and keeps the payload as pure audit data.
     */
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropIndex(['reference']);
            $table->dropColumn('reference');
        });
    }
};
