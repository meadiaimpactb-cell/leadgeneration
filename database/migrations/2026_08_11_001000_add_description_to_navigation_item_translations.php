<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A one-line description under a menu entry.
 *
 * The solutions panel turns four links into a chooser: a visitor hesitating
 * between «الشركاء» and «شركات القطاع الخاص» should know which door is theirs
 * before they click, which is one fewer wrong step on the way to the form.
 *
 * Translated, because it is copy (§22.5), and nullable, because every other
 * menu entry on the site has no use for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('navigation_item_translations', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('navigation_item_translations', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
