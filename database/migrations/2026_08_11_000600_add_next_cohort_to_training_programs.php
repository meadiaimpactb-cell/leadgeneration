<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the next cohort of a track opens — optional, and blank by default.
 *
 * Per locale, because the value is a month or a state of play («يناير ٢٠٢٧»,
 * «التسجيل مفتوح»), and a month name is a language's business, not a date
 * format's.
 *
 * Empty is the shipped state and the honest one: a track card that says
 * "enrolment always open" when nobody is scheduling cohorts, or that carries a
 * date typed into a template months ago, is a promise the page cannot keep.
 * Nothing renders until the client fills it, and clearing it removes the line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_program_translations', function (Blueprint $table): void {
            $table->string('next_cohort')->nullable()->after('outcomes');
        });
    }

    public function down(): void
    {
        Schema::table('training_program_translations', function (Blueprint $table): void {
            $table->dropColumn('next_cohort');
        });
    }
};
