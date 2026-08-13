<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A keyword aimed at one page, and how well that page currently serves it.
 *
 * The sibling of `keywords`, not its replacement. That table is deliberately
 * flat and global — its own migration explains why: you learn what buyers
 * search for before you know which page should answer it, and a form that
 * demands a page first puts the work in the wrong order.
 *
 * This table is the step after. Once an editor knows a page should rank for
 * a phrase, the question changes from "does the site say this anywhere" to
 * "is this page actually built around it" — and that has an answer per field:
 * the title, the description, the body, the image alts. Both screens stay.
 *
 * WHY THE RESULT IS STORED AND NOT COMPUTED ON EVERY RENDER
 *
 * Analysing one keyword reads the page, its translation and every section
 * translation. A screen listing sixty keywords would do that sixty times.
 * The score is written when the keyword is added and rewritten whenever the
 * page changes, so opening the screen is a single select — and so the colour
 * an editor saw yesterday is the colour they see today unless the page moved.
 *
 * `checks` holds each individual test's outcome so the detail panel can be
 * opened without re-running anything, and so a later scoring change can be
 * told apart from a content change in the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_keywords', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);

            // As typed, including diacritics and whichever hamza form the
            // editor used — it is shown back to them and must look like what
            // they wrote.
            $table->string('keyword', 191);

            /*
             * The form everything is matched on: diacritics stripped, hamza
             * and taa marbuta and alef maqsura unified, tatweel removed.
             *
             * Stored rather than derived at query time because it is also the
             * uniqueness key. Without it «هدايا مؤسسية» and «هدايا مؤسّسية»
             * are two rows in the list and two different scores for one
             * phrase — see KeywordAnalyzer::normalise().
             */
            $table->string('keyword_normalized', 191);

            $table->unsignedTinyInteger('score')->default(0);
            $table->json('checks')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            // Same phrase, same page, same language — once. Two spellings of
            // one phrase collapse here rather than in the interface.
            $table->unique(['page_id', 'locale', 'keyword_normalized'], 'page_keywords_unique');
            $table->index(['page_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_keywords');
    }
};
