<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.4 — admin user fields and per-locale media alt text.
 *
 * Every image carries alt text managed from the admin panel (§10.8), which
 * means alt text is content and therefore translatable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('preferred_locale', 5)->default('ar')->after('last_login_at');
        });

        Schema::create('media_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            // Decorative images store an empty string, not null — the
            // difference between "alt=''" and "alt missing" matters (§10.8).
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->timestamps();

            $table->unique(['media_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_translations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'last_login_at', 'preferred_locale']);
        });
    }
};
