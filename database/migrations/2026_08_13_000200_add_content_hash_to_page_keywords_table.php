<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fingerprint of everything the analyser read, the last time it ran.
 *
 * Two jobs, one column.
 *
 * SKIPPING WORK THAT CANNOT CHANGE AN ANSWER
 *
 * Saving a page dispatches a re-analysis of its keywords. Most saves change
 * something the analyser does not read — a publish date, a sort order, an
 * image position — and re-scoring sixty keywords to write back sixty identical
 * numbers is work for nothing. The fingerprint covers exactly the fields that
 * feed the score, so "did this save change any answer" is one comparison.
 *
 * TELLING A STALE SCORE FROM A CURRENT ONE
 *
 * The re-analysis is queued, which means there is a moment — or an afternoon,
 * if nobody started a worker — where the page says one thing and the stored
 * score describes what it said before. Without this column that state is
 * invisible: a stale colour looks exactly like a wrong one, which is the one
 * failure this screen cannot survive. With it, the screen compares the page's
 * fingerprint now against the one stored per keyword and says plainly that the
 * number is being recalculated.
 *
 * Nullable because every row written before this migration has no fingerprint,
 * and null must mean "unknown, so re-analyse" rather than "unchanged".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_keywords', function (Blueprint $table): void {
            // sha1 in hex. Wide enough to swap the algorithm without a second
            // migration, narrow enough to stay indexable if it ever needs to be.
            $table->string('content_hash', 64)->nullable()->after('checks');
        });
    }

    public function down(): void
    {
        Schema::table('page_keywords', function (Blueprint $table): void {
            $table->dropColumn('content_hash');
        });
    }
};
