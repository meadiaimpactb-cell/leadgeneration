<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site's target keyword list (§13).
 *
 * Flat and global on purpose. The first version required an editor to pick a
 * page before they could write a keyword, which put the work in the wrong
 * order: you learn what your buyers search for first, and only then discover
 * whether you have a page that answers it.
 *
 * So a keyword is just a phrase and a language. What connects it to a page is
 * computed, not stored — the panel searches the published pages for it and
 * reports which ones cover it. That report is the useful artefact: a keyword
 * matching no page is a content gap, and a content gap is the only thing on
 * this screen that actually costs rankings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 5);
            $table->string('term', 191);
            // Client's own grouping — "government", "gifts", a campaign name.
            // Free text because their taxonomy is theirs, not ours.
            $table->string('group', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['locale', 'term']);
            $table->index(['locale', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
