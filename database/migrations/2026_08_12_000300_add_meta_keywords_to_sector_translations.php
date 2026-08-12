<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target keywords for a segment page, per language (§13).
 *
 * `page_translations` has carried these since August; `sector_translations`
 * never did, so the four segment pages — the ones written to be found by a
 * procurement officer searching — were the only public pages with a meta
 * title and description but no keywords. `PublicController::seo()` sources
 * keywords from the current *page* record, and a segment page has none, so
 * the value could not be supplied even by hand.
 *
 * Per translation, for the reason the pages migration gives: Arabic and
 * English chase different search terms.
 *
 * TWO NAMING NOTES, both deliberate
 *
 * `meta_keywords`, not `keywords` as on pages. This table already holds
 * `meta_title` and `meta_description`, and the three are one set — a bare
 * `keywords` beside them would read as something else, e.g. the craft
 * keywords a segment is associated with. Anyone generalising the two tables
 * later needs to know they differ by name and not by meaning.
 *
 * 255 rather than the 512 pages use. The panel's generic content editor
 * validates every single-line translated field at 255, and a column that
 * accepts what the only form writing to it rejects is a limit no one can
 * find. If the editors ever need more, both numbers move together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sector_translations', function (Blueprint $table): void {
            $table->string('meta_keywords', 255)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('sector_translations', function (Blueprint $table): void {
            $table->dropColumn('meta_keywords');
        });
    }
};
