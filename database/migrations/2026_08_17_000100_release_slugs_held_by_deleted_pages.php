<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Release the URL identifiers still held by pages that were deleted before
 * `Page` learned to release them.
 *
 * The model now parks a slug when a page is soft-deleted, but rows deleted
 * earlier kept theirs — which is exactly the state the client was stuck in: a
 * page called "services" was deleted, was invisible in every panel screen, and
 * still made "services" impossible to use.
 *
 * Nothing is destroyed. The row stays, the deletion stays reversible, and the
 * original slug stays legible inside the parked value.
 */
return new class extends Migration
{
    /**
     * Kept as a literal rather than read from `Page::PARKED`. A migration
     * records what was done on the day it ran; if the model's convention ever
     * changes, this file must keep describing the rows it actually wrote.
     */
    private const PARKED = '__deleted__';

    public function up(): void
    {
        $held = DB::table('pages')
            ->whereNotNull('deleted_at')
            ->where('slug', 'not like', '%'.self::PARKED.'%')
            ->get(['id', 'slug']);

        foreach ($held as $page) {
            DB::table('pages')->where('id', $page->id)->update([
                'slug' => mb_substr($page->slug, 0, 150).self::PARKED.$page->id,
            ]);
        }
    }

    public function down(): void
    {
        $parked = DB::table('pages')
            ->whereNotNull('deleted_at')
            ->where('slug', 'like', '%'.self::PARKED.'%')
            ->get(['id', 'slug']);

        foreach ($parked as $page) {
            $original = Str::before($page->slug, self::PARKED);

            // Only if nothing has claimed the name since — a live page under
            // it outranks a row on its way back to the bin.
            $taken = DB::table('pages')
                ->where('slug', $original)
                ->where('id', '!=', $page->id)
                ->exists();

            if (! $taken) {
                DB::table('pages')->where('id', $page->id)->update(['slug' => $original]);
            }
        }
    }
};
