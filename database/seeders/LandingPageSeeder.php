<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * The single landing page (management decision, 7 September 2026).
 *
 * Writes ONLY the page and its blocks. The site-wide switchover this decision
 * also requires — rewriting the header menu and cutting the form back to two
 * fields — lives in LandingSwitchoverSeeder, because those two change state
 * that a great many other tests legitimately depend on.
 *
 * Writes the approved copy from database/seeders/data/landing-content.php into
 * `sections` + `section_translations`, so it is the client's to edit in the
 * admin panel from the moment it lands — not a string in a component (§2.3,
 * "no hardcoded content").
 *
 * SAFE TO RE-RUN. Every write is create-if-absent: a section the client has
 * since reworded, reordered or switched off is left exactly as they left it.
 * The seeder's job is to put the approved text there once, not to hold the
 * page hostage to this file forever. Structural columns — the section's type
 * and its position — are owned here only when the row is first created.
 */
class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $page = $this->page();

        $this->sections($page);
    }

    /**
     * The page row the landing sections hang from.
     *
     * Published on creation, because an unpublished landing page means the
     * site's only URL answers with an empty document. Never re-published on a
     * later run — unpublishing is a decision the panel makes.
     */
    private function page(): Page
    {
        $page = Page::query()->firstOrCreate(
            ['slug' => 'landing'],
            [
                'template' => 'landing',
                'sort_order' => 0,
                'status' => 'published',
                'published_at' => now(),
                'is_indexable' => true,
            ],
        );

        /*
         * The page this one replaces stops being published.
         *
         * `home` and `landing` both resolve to `/{locale}` — Page::ROOT_SLUGS
         * lists them together — so two published rows claim one address. That
         * is an invalid state, not a preference: the sitemap listed `/ar`
         * twice because of it, and the pages screen showed two live pages one
         * of which nothing could reach.
         *
         * Here rather than in LandingSwitchoverSeeder, and unconditional,
         * because retiring the page being replaced is part of the act of
         * seeding its replacement — not a separate site-wide switch. Putting
         * it in the switchover left the test database with both published and
         * the sitemap screen counting one URL for two rows.
         *
         * Reversing it is the publish button.
         */
        Page::query()->where('slug', 'home')->update(['status' => 'draft']);

        // Titles only — the meta description is content and arrives from Amad
        // Craft through the panel (§22.1).
        $page->translations()->firstOrCreate(['locale' => 'ar'], ['title' => 'أمد الحرف']);
        $page->translations()->firstOrCreate(['locale' => 'en'], ['title' => 'Amad Craft']);

        return $page;
    }

    /** The 27 approved blocks, in the approved order. */
    private function sections(Page $page): void
    {
        $content = require database_path('seeders/data/landing-content.php');

        $order = 0;

        foreach ($content as $row) {
            $order += 10;

            /*
             * Identified by its position, not by its type.
             *
             * The page uses `rich_text` eleven times and `cards` five times,
             * so type alone identifies nothing. `sort_order` is unique per
             * row here and is what makes a re-run update the block it first
             * created rather than the first one that happens to match.
             */
            $section = $page->sections()->firstOrCreate(
                ['sort_order' => $order],
                [
                    'type' => $row['type'],
                    'is_active' => true,
                    'settings' => array_merge($row['settings'], ['anchor' => $row['anchor']]),
                ],
            );

            // A section the client has since edited keeps their words.
            if (! $section->wasRecentlyCreated) {
                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                $section->translations()->firstOrCreate(
                    ['locale' => $locale],
                    [
                        'heading' => $row[$locale]['heading'] ?? null,
                        'subheading' => $row[$locale]['subheading'] ?? null,
                        'body' => $row[$locale]['body'] ?? null,
                    ],
                );
            }
        }
    }
}
