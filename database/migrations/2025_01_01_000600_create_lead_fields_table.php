<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client-managed form builder for the one lead form.
 *
 * §6.1 fixes the shipped form at a single contact field plus an optional
 * one-line message, and states plainly that a name is never asked. That is the
 * default this table is seeded with, and it is the configuration the site is
 * delivered in.
 *
 * What this table adds is the ability for Amad Craft to change that decision
 * later from the admin panel, without a developer — turn a field on, reorder
 * it, rename it in both languages — rather than the decision being welded into
 * the code. The brief's rule stays the default; it stops being a hard-coded
 * constraint on the client.
 *
 * Every field added here costs leads. The admin screen says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_fields', function (Blueprint $table): void {
            $table->id();
            // Stable machine name. `contact` and `message` are reserved and
            // map onto real columns on `leads`; anything else lands in `extra`.
            $table->string('key', 64)->unique();
            $table->string('type', 32)->default('text');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_required')->default(false);
            // The contact field cannot be removed or switched off — without it
            // there is no lead at all (§1).
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            // Choices for select/radio types, and any extra validation.
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('max_length')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::create('lead_field_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_field_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->string('help')->nullable();
            $table->timestamps();

            $table->unique(['lead_field_id', 'locale'], 'lead_field_translations_unique');
        });

        Schema::table('leads', function (Blueprint $table): void {
            // Answers to any field beyond the two the brief defines. Kept as
            // JSON rather than columns so enabling a field never needs a
            // migration — which is the whole point of the builder.
            $table->json('extra')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn('extra');
        });

        Schema::dropIfExists('lead_field_translations');
        Schema::dropIfExists('lead_fields');
    }
};
