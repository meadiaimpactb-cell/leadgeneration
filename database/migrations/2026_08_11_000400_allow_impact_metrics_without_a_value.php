<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A figure the client has not measured yet is stored with no number.
 *
 * /training shows three training figures, and Amad Craft holds one of them
 * today. The other two — how many trainees finished a track, how many became
 * producing artisans with us — are exactly the kind of number that must not be
 * invented (§22.1), and they belong in the same register as every other figure
 * on the site so they cannot later be published from a second place.
 *
 * So the rows exist with their labels and no value, `ImpactMetric::visible()`
 * skips them, and the figure appears the moment someone types it in the panel.
 * The alternative — leaving the row inactive — asks an operator to know that
 * filling a number is not enough, which is how a measured figure stays hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impact_metrics', function (Blueprint $table): void {
            $table->decimal('value_numeric', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // A row with no value cannot survive a NOT NULL column, so it is given
        // one first rather than left to fail the ALTER under strict mode.
        DB::table('impact_metrics')->whereNull('value_numeric')->update(['value_numeric' => 0]);

        Schema::table('impact_metrics', function (Blueprint $table): void {
            $table->decimal('value_numeric', 15, 2)->nullable(false)->change();
        });
    }
};
