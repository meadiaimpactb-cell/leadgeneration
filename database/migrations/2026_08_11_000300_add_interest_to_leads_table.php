<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of a page's two audiences a lead came from.
 *
 * §6.1 fixes the form at one required contact field plus an optional line, and
 * that is not negotiable — but /training addresses two people who want
 * opposite things: an artisan asking to join a track, and an institution
 * asking to sponsor one. Both submit the same three fields, so without this
 * the sales team receives two indistinguishable enquiries and has to ask.
 *
 * Recorded from which button the visitor pressed rather than by adding a
 * fourth field to the form: qualification the visitor never had to do.
 *
 * Deliberately a free string and not an enum. The value is set from a section
 * setting in the panel, so a constrained column would turn a typo there into a
 * rejected submission — and losing a lead is the one failure this site cannot
 * afford (§1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('interest', 32)->nullable()->after('sector_hint');

            // Filtered on in the panel's lead list, alongside the date range.
            $table->index('interest');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['interest']);
            $table->dropColumn('interest');
        });
    }
};
