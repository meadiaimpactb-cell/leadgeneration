<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sectors become a taxonomy on solutions.
 *
 * "القطاعات" left the sidebar as an entry of its own; each solution now
 * carries the audience segments it serves, so the four segments are reached
 * from inside Solutions rather than from a parallel menu item.
 *
 * Nothing is deleted. The `sectors` table, its translations, its sections and
 * its media are untouched — the four public pages at /solutions/{segment} are
 * those very records, and they keep their own editor. This adds a link
 * between the two, it does not replace one with the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solution_sector', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('solution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();

            // Order within the solution, so the client controls which audience
            // is named first on a solution page.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // One row per pair. Without it a double save silently doubles the
            // list, and nothing downstream would notice.
            $table->unique(['solution_id', 'sector_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solution_sector');
    }
};
