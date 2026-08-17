<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving a lead — the panel's answer to "this row should not be in my list".
 *
 * Deliberately NOT `deleted_at` and NOT `SoftDeletes`.
 *
 * §9.1 and LeadPolicy both say a lead is never deleted: it is the site's only
 * record of a real prospect, and its audit trail has to survive a change of
 * mind. `SoftDeletes` would satisfy that on disk while calling the act
 * "delete" everywhere in the code and hiding the row from every query in the
 * application by default — including the counts on the dashboard, which are
 * the numbers §1 measures the whole site by.
 *
 * `archived_at` is a curation state, like `status`. It hides a row from one
 * list, in one screen, on purpose. Nothing is removed, no global scope is
 * added, and the dashboard keeps counting what actually arrived.
 *
 * Indexed with `status` because the leads list filters on both together and
 * that pair is the only query that reads this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('status');
            $table->index(['archived_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['archived_at', 'status']);
            $table->dropColumn('archived_at');
        });
    }
};
