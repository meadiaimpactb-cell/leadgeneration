<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.1 — pages and the polymorphic section builder.
 *
 * `sections` is what makes the site 100% dynamic (§0.3): any page, solution,
 * sector or campaign composes itself from ordered, typed sections that the
 * admin panel can add, reorder and remove without code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 191);
            $table->string('template', 64)->default('default');
            $table->foreignId('parent_id')->nullable()
                ->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->json('layout_settings')->nullable();
            // Secret preview link for unpublished work (§9.1).
            $table->uuid('preview_token')->nullable()->unique();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug');
            $table->index(['status', 'published_at']);
        });

        Schema::create('page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 512)->nullable();
            $table->unsignedBigInteger('og_image_id')->nullable();
            $table->string('canonical_override', 512)->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            // Attaches to a page, solution, sector, campaign …
            $table->morphs('sectionable');
            $table->string('type', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['sectionable_type', 'sectionable_id', 'sort_order'], 'sections_order_index');
        });

        Schema::create('section_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('heading')->nullable();
            $table->string('subheading')->nullable();
            $table->longText('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 512)->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_translations');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('page_translations');
        Schema::dropIfExists('pages');
    }
};
