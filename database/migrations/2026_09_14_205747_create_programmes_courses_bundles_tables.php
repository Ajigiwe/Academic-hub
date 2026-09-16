<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users get the domain columns the marketplace needs (role, names).
        // Default Laravel columns are renamed to match the Next.js domain.
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('name');
            $table->string('last_name')->after('first_name');
            $table->string('role')->default('STUDENT')->after('password');
            $table->string('status')->default('ACTIVE')->after('role');
            $table->index('status');
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();      // "ACC 102"
            $table->string('title');               // "Introduction to Financial Accounting"
            $table->string('slug')->unique();
            $table->index('title');
            $table->timestamps();
        });

        // The unit of sale: one bundle per course + academic year.
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('solved')->default(false);
            $table->string('status')->default('DRAFT');     // DRAFT|PUBLISHED|UNPUBLISHED|ARCHIVED
            $table->unsignedInteger('level');               // 100..400
            $table->string('academic_year');                // "2024/2025"
            $table->unsignedInteger('price_pesewas');       // integer pesewas, never floats
            $table->timestamp('published_at')->nullable();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('academic_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundles');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programmes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['first_name', 'last_name', 'role', 'status']);
        });
    }
};
