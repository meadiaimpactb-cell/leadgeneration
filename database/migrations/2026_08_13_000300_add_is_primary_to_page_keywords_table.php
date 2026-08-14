<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which one of a page's keywords is the one it is actually about.
 *
 * A page can carry sixty phrases and still have exactly one subject. Without
 * somewhere to say which, the screen is a flat list where the phrase the page
 * was written for looks no different from a phrase somebody pasted in on a
 * hunch — and two people editing the same page have no way to agree on what it
 * is for.
 *
 * One per page per language. Not enforced by an index: MySQL has no partial
 * unique, and a unique on (page_id, locale, is_primary) would forbid a second
 * non-primary keyword, which is the opposite of what is wanted. The controller
 * clears the previous holder inside a transaction instead.
 *
 * Deliberately a label and nothing more. It does not feed
 * `page_translations.keywords`, it does not change any score, and it does not
 * reach the public site — see docs/seo-per-page-module.md on why the three
 * homes for keywords stay separate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_keywords', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false)->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('page_keywords', function (Blueprint $table): void {
            $table->dropColumn('is_primary');
        });
    }
};
