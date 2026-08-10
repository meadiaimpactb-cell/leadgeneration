<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.4 — settings, redirects, navigations, CTAs.
 *
 * `settings` holds everything the client changes without a deploy: contact
 * details, social links, tracking IDs, SEO defaults, feature switches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->json('value')->nullable();
            // Only public rows reach the browser. API keys stay server-side.
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('is_public');
        });

        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            // from_path is matched on every 404 so it carries an index; 255
            // utf8mb4 chars stays inside MySQL's 3072-byte key limit.
            $table->string('from_path', 255);
            $table->string('to_path', 512);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedBigInteger('hits')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['from_path', 'is_active']);
        });

        Schema::create('navigations', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();   // header | footer_main | footer_legal
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('navigation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()
                ->constrained('navigation_items')->cascadeOnDelete();
            $table->string('url', 512)->nullable();
            $table->string('route_name', 128)->nullable();
            $table->nullableMorphs('linkable');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['navigation_id', 'parent_id', 'sort_order']);
        });

        Schema::create('navigation_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('navigation_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->timestamps();

            $table->unique(['navigation_item_id', 'locale'], 'nav_item_translations_unique');
        });

        Schema::create('ctas', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('style', 32)->default('cta');       // primary | cta | cta-lg | secondary | ghost | link
            $table->string('target_type', 32)->default('lead'); // lead | route | url | anchor
            $table->string('target_value', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cta_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cta_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->timestamps();

            $table->unique(['cta_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cta_translations');
        Schema::dropIfExists('ctas');
        Schema::dropIfExists('navigation_item_translations');
        Schema::dropIfExists('navigation_items');
        Schema::dropIfExists('navigations');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('settings');
    }
};
