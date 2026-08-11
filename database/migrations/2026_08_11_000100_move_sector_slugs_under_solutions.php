<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The four audience segments moved from `/sectors/{slug}` to
 * `/solutions/{slug}`, and two of them were shortened at the same time:
 *
 *   government-entities → government
 *   private-sector      → companies
 *
 * A migration rather than a seeder, because StructureSeeder is production-safe
 * by being `firstOrCreate` — it will not, and must not, rewrite a row the
 * client may have edited. Renaming existing rows is a one-time data change,
 * which is what migrations are for.
 *
 * Guarded on the old value, so it is a no-op on a fresh install (where the
 * seeder already wrote the new slug) and on a second run.
 */
return new class extends Migration
{
    private const MOVES = [
        'government-entities' => 'government',
        'private-sector' => 'companies',
    ];

    public function up(): void
    {
        foreach (self::MOVES as $old => $new) {
            $this->rename($old, $new);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::MOVES) as $new => $old) {
            $this->rename($new, $old);
        }
    }

    private function rename(string $from, string $to): void
    {
        // Skipped rather than allowed to collide: if a row already holds the
        // target slug, renaming into it would break the unique index.
        if (DB::table('sectors')->where('slug', $to)->exists()) {
            return;
        }

        DB::table('sectors')->where('slug', $from)->update(['slug' => $to]);
    }
};
