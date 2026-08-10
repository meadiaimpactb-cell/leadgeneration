<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target keywords, per page and per language (§13).
 *
 * §13 engages an SEO specialist and says their keyword list "is applied across
 * titles, descriptions and page structure". That is what this column holds:
 * the words a page is meant to rank for, so the editor sees them while writing
 * that page's title and description, and the panel can tell them whether the
 * words actually made it in.
 *
 * Per translation rather than per page, because the Arabic and English
 * versions chase different search terms — "هدايا مؤسسية" is not what an
 * English-speaking procurement officer types.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_translations', function (Blueprint $table): void {
            $table->string('keywords', 512)->nullable()->after('meta_description');
        });
    }

    public function down(): void
    {
        Schema::table('page_translations', function (Blueprint $table): void {
            $table->dropColumn('keywords');
        });
    }
};
