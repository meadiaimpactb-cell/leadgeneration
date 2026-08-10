<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.2 — the domain entities.
 *
 * Note what is absent and must stay absent (§2.2): no price, no stock, no
 * cart, no order. `showcase_products` carries an informational store link and
 * nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Solutions ------------------------------------------------
        Schema::create('solutions', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hero_media_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('solution_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('solution_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->timestamps();

            $table->unique(['solution_id', 'locale']);
        });

        // ---- Sectors (§3: government | private | partners | artisans) --
        Schema::create('sectors', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->enum('key', ['government', 'private', 'partners', 'artisans'])->unique();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hero_media_id')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('sector_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->timestamps();

            $table->unique(['sector_id', 'locale']);
        });

        // ---- Showcase products (informational only) --------------------
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_category_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->timestamps();

            $table->unique(['product_category_id', 'locale'], 'product_cat_translations_unique');
        });

        Schema::create('showcase_products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->foreignId('product_category_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Informational link to the Zid store. No price, no buy (§4).
            $table->string('external_store_url', 512)->nullable();
            $table->unsignedBigInteger('primary_media_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('showcase_product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('showcase_product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('craft_technique')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->timestamps();

            $table->unique(['showcase_product_id', 'locale'], 'product_translations_unique');
        });

        // ---- Impact ----------------------------------------------------
        Schema::create('impact_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->decimal('value_numeric', 15, 2);
            $table->string('value_suffix', 16)->nullable();  // %, +, ألف …
            $table->unsignedSmallInteger('year')->nullable();
            // Optionally scoped to one sector for sector-page numbers (§11.2).
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('impact_metric_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('impact_metric_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['impact_metric_id', 'locale'], 'impact_translations_unique');
        });

        // ---- Artisan stories -------------------------------------------
        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->unsignedBigInteger('person_media_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('story_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->longText('body')->nullable();
            $table->text('quote')->nullable();
            $table->string('attribution')->nullable();
            $table->timestamps();

            $table->unique(['story_id', 'locale']);
        });

        // ---- Reports ----------------------------------------------------
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('file_media_id')->nullable();
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_public', 'year']);
        });

        Schema::create('report_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'locale']);
        });

        // ---- Training ----------------------------------------------------
        Schema::create('training_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('hero_media_id')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('training_program_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_program_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->longText('outcomes')->nullable();
            $table->timestamps();

            $table->unique(['training_program_id', 'locale'], 'training_translations_unique');
        });

        // ---- Partners & accreditations ------------------------------------
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->string('website_url', 512)->nullable();
            $table->enum('type', ['partner', 'accreditation', 'client'])->default('partner');
            // Client logos can be scoped to the sector page they belong on.
            $table->foreignId('sector_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::create('partner_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('display_name');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_translations');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('training_program_translations');
        Schema::dropIfExists('training_programs');
        Schema::dropIfExists('report_translations');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('story_translations');
        Schema::dropIfExists('stories');
        Schema::dropIfExists('impact_metric_translations');
        Schema::dropIfExists('impact_metrics');
        Schema::dropIfExists('showcase_product_translations');
        Schema::dropIfExists('showcase_products');
        Schema::dropIfExists('product_category_translations');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('sector_translations');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('solution_translations');
        Schema::dropIfExists('solutions');
    }
};
