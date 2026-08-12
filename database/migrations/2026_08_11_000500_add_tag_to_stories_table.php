<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One label per story, so a page can ask for the stories that belong on it.
 *
 * /training needs the graduates of its own tracks — the strongest thing it can
 * put in front of an institution considering sponsoring one — and /impact
 * needs every story. A copy of the story on the training page would be two
 * records of one person's words; a tag is one record, read twice.
 *
 * A single column rather than a tags table: there is exactly one axis being
 * asked for, and a pivot the client has to learn in order to type one word is
 * a worse panel, not a more capable one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table): void {
            $table->string('tag', 64)->nullable()->after('slug');

            $table->index(['tag', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table): void {
            $table->dropIndex(['tag', 'is_published']);
            $table->dropColumn('tag');
        });
    }
};
