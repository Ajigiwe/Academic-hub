<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Port of Prisma `Setting` — platform key/value settings.
        // Programme visibility is the primary consumer: key =
        // `programme.<slug>.enabled`, value "true"|"false". A MISSING row
        // counts as enabled (safe default); the seed writes rows so fresh
        // installs start with the canonical programmes enabled.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });

        // Port of Prisma `Favourite` — student bookmarks a resource
        // (paper in their library, or a free material).
        Schema::create('favourites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favourites');
        Schema::dropIfExists('settings');
    }
};
