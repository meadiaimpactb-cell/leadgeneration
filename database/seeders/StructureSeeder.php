<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Sector;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Structural scaffolding only (§7.4: "seeders for structural data only, no
 * dummy marketing content in production").
 *
 * This creates the empty containers Amad Craft will fill: the page rows, the
 * four sector rows, and the setting keys the admin panel exposes. It writes
 * NO headlines, NO body copy and NO marketing text — all of that arrives from
 * Amad Craft (§0.1, §22.1).
 *
 * Sector and page NAMES are unavoidable here because a row needs a label to
 * be findable in the admin panel. They are the plain segment names from §3,
 * not copy, and the client overwrites them on first edit.
 */
class StructureSeeder extends Seeder
{
    public function run(): void
    {
        $this->pages();
        $this->sectors();
        $this->settings();
    }

    /**
     * The sitemap from §5. Each page starts as a draft with no sections —
     * the client composes it in the section builder.
     */
    private function pages(): void
    {
        $pages = [
            'home' => ['العنوان الرئيسي', 'Home'],
            'about' => ['من نحن', 'About'],
            'solutions' => ['الحلول للشركات', 'Solutions'],
            'products' => ['المنتجات', 'Products'],
            'impact' => ['الأثر والتقارير', 'Impact & reports'],
            'training' => ['التدريب والتمكين', 'Training'],
            'partners' => ['الشركاء والاعتمادات', 'Partners & accreditations'],
            'contact' => ['التواصل', 'Contact'],
        ];

        $order = 0;

        foreach ($pages as $slug => [$ar, $en]) {
            $page = Page::query()->firstOrCreate(
                ['slug' => $slug],
                ['template' => $slug, 'sort_order' => $order++, 'status' => 'draft']
            );

            // Placeholder titles so the row is identifiable in the admin list.
            // Real titles are entered by Amad Craft.
            $page->translations()->firstOrCreate(['locale' => 'ar'], ['title' => $ar]);
            $page->translations()->firstOrCreate(['locale' => 'en'], ['title' => $en]);
        }
    }

    /** The four target segments in §3's priority order. */
    private function sectors(): void
    {
        $sectors = [
            Sector::KEY_GOVERNMENT => ['government-entities', 'الجهات الحكومية', 'Government entities'],
            Sector::KEY_PRIVATE => ['private-sector', 'القطاع الخاص', 'Private sector'],
            Sector::KEY_PARTNERS => ['partners', 'الشركاء', 'Partners'],
            Sector::KEY_ARTISANS => ['artisans', 'الحرفيون', 'Artisans'],
        ];

        $order = 0;

        foreach ($sectors as $key => [$slug, $ar, $en]) {
            $sector = Sector::query()->firstOrCreate(
                ['key' => $key],
                ['slug' => $slug, 'sort_order' => $order++, 'is_active' => true]
            );

            $sector->translations()->firstOrCreate(['locale' => 'ar'], ['name' => $ar]);
            $sector->translations()->firstOrCreate(['locale' => 'en'], ['name' => $en]);
        }
    }

    /**
     * The setting keys the admin panel manages (§14.1).
     *
     * Created empty. Filling them — tracking IDs, contact details, the store
     * link — is the client's job, and none of them belong in code (§22.9).
     */
    private function settings(): void
    {
        $settings = [
            // group, key, default, is_public
            ['site', 'name.ar', null, true],
            ['site', 'name.en', null, true],
            ['site', 'copyright.ar', null, true],
            ['site', 'copyright.en', null, true],
            // English launch timing is a management decision (§12, §20.5).
            ['site', 'english_enabled', true, true],

            ['contact', 'email', null, true],
            ['contact', 'phone', null, true],
            ['contact', 'whatsapp', null, true],
            ['contact', 'address.ar', null, true],
            ['contact', 'address.en', null, true],
            ['contact', 'hours.ar', null, true],
            ['contact', 'hours.en', null, true],
            ['contact', 'social', [], true],

            // The always-on-screen contact dock. Its heading and note are
            // copy, so they live here rather than in the component (§0.1),
            // and the whole dock is switchable in case the client decides a
            // persistent CTA is too forward for an institutional audience.
            ['site', 'contact_dock', true, true],
            ['contact', 'dock_heading.ar', null, true],
            ['contact', 'dock_heading.en', null, true],
            ['contact', 'dock_note.ar', null, true],
            ['contact', 'dock_note.en', null, true],

            // Where the company is (§5 contact page). A plain search query is
            // enough for the embed; paste a full Maps embed URL into
            // map_embed_url once the exact pin is confirmed.
            ['contact', 'map_query', null, true],
            ['contact', 'map_embed_url', null, true],
            ['contact', 'map_url', null, true],

            // Informational link to the Zid store. No price, no buy (§4).
            ['store', 'url', null, true],
            ['store', 'label.ar', null, true],
            ['store', 'label.en', null, true],

            // robots.txt body. Empty means "use the built-in rules"; anything
            // written here replaces them (§13). The Sitemap: line is appended
            // either way, so it cannot be lost by an edit.
            ['seo', 'robots_txt', null, false],

            ['seo', 'default_description.ar', null, true],
            ['seo', 'default_description.en', null, true],
            ['seo', 'default_og_image', null, true],
            ['seo', 'organization_schema', null, false],

            // Tracking IDs live here, never in code (§14.1, §22.9).
            // Search Console ownership verification (§14.1). Public, because
            // it is emitted as a meta tag in the page head anyway.
            ['tracking', 'search_console', null, true],

            ['tracking', 'gtm_id', null, false],
            ['tracking', 'ga4_id', null, false],
            ['tracking', 'meta_pixel_id', null, false],
            ['tracking', 'clarity_id', null, false],
            ['tracking', 'meta_capi_token', null, false],
        ];

        foreach ($settings as [$group, $key, $value, $isPublic]) {
            Setting::query()->firstOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value, 'is_public' => $isPublic]
            );
        }
    }
}
