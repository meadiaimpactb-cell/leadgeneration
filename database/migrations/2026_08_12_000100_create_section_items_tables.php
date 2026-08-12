<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repeatable section content as records instead of a JSON array.
 *
 * Seventeen sections keep their cards, steps, pledges and occasions inside
 * `sections.settings->items[]`. That worked, and it cost four things the site
 * now needs: no single item can be switched off, none can be ordered without
 * rewriting the array, none can own a media-library image, and — because
 * `settings` is one column shared by both locales — each item had to carry
 * `title` and `title_en` side by side, which is the one place in this codebase
 * that departs from the translation-table pattern.
 *
 * Mirrors `sections` / `section_translations` deliberately: same shape, same
 * naming, same cascade. Anyone who understands one understands the other.
 *
 * Nothing is migrated or deleted here. The JSON stays exactly where it is
 * until the copy is verified — see the removal plan in docs/dynamic-audit.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();

            /*
             * The icon key, not the glyph. `CardsGrid` renders a `NavIcon` by
             * name — an emoji here would put a second brand's artwork inside a
             * card built from these tokens, which is a defect this project has
             * already fixed once.
             */
            $table->string('icon')->nullable();

            /*
             * Per-item settings for the handful of types that need one extra
             * value — a year on a timeline station, a region on a map point.
             * Anything repeatable enough to deserve a column gets a column.
             */
            $table->json('settings')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            // The point of the exercise: one item can be switched off without
            // touching the section or its siblings.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['section_id', 'sort_order']);
        });

        Schema::create('section_item_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);

            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();

            $table->timestamps();

            // One row per locale per item. §12 serves nothing rather than
            // falling back, so a missing row is a meaningful absence.
            $table->unique(['section_item_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_item_translations');
        Schema::dropIfExists('section_items');
    }
};
