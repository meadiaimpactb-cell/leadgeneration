<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Sector;
use App\Models\Setting;
use Database\Seeders\Concerns\SeedsRows;
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
    use SeedsRows;

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
            // Nothing is enforced here, deliberately. All three columns are
            // editable in the panel: `template` and `sort_order` in the page
            // editor, and `status` is the publish button itself. Re-running
            // this seeder must never unpublish a live page or undo a reorder.
            $page = $this->seedRow(
                Page::query(),
                identity: ['slug' => $slug],
                owned: ['template' => $slug, 'sort_order' => $order++, 'status' => 'draft'],
            );

            // Placeholder titles so the row is identifiable in the admin list.
            // Real titles are entered by Amad Craft.
            $this->seedRow($page->translations(), identity: ['locale' => 'ar'], owned: ['title' => $ar]);
            $this->seedRow($page->translations(), identity: ['locale' => 'en'], owned: ['title' => $en]);
        }
    }

    /** The four target segments in §3's priority order. */
    private function sectors(): void
    {
        $sectors = [
            // Slugs shortened when the segments moved under /solutions. The
            // old paths are kept alive by rows in the `redirects` table
            // (§22.7) — see RedirectsSeeder.
            Sector::KEY_GOVERNMENT => ['government', 'الجهات الحكومية', 'Government entities'],
            Sector::KEY_PRIVATE => ['companies', 'القطاع الخاص', 'Private sector'],
            Sector::KEY_PARTNERS => ['partners', 'الشركاء', 'Partners'],
            Sector::KEY_ARTISANS => ['artisans', 'الحرفيون', 'Artisans'],
        ];

        $order = 0;

        foreach ($sectors as $key => [$slug, $ar, $en]) {
            // `key` is the identity and is not editable — ContentRegistry
            // leaves it out of the sector form on purpose, because code
            // branches on it. Everything else is the client's: the panel
            // exposes the slug, the order and the active toggle, and a
            // re-seed that reset them would resurrect a sector they had
            // switched off and break the slug the redirects point at.
            $sector = $this->seedRow(
                Sector::query(),
                identity: ['key' => $key],
                owned: ['slug' => $slug, 'sort_order' => $order++, 'is_active' => true],
            );

            $this->seedRow($sector->translations(), identity: ['locale' => 'ar'], owned: ['name' => $ar]);
            $this->seedRow($sector->translations(), identity: ['locale' => 'en'], owned: ['name' => $en]);
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
            ['contact', 'header_cta.ar', null, true],
            ['contact', 'header_cta.en', null, true],
            ['site', 'footer_blurb.ar', null, true],
            ['site', 'footer_blurb.en', null, true],
            ['contact', 'location_heading.ar', null, true],
            ['contact', 'location_heading.en', null, true],
            ['contact', 'location_note.ar', null, true],
            ['contact', 'location_note.en', null, true],
            ['contact', 'directions_label.ar', null, true],
            ['contact', 'directions_label.en', null, true],

            // The visit request and the WhatsApp opener on the contact page.
            ['contact', 'visit_cta.ar', null, true],
            ['contact', 'visit_cta.en', null, true],
            ['contact', 'visit_prompt.ar', null, true],
            ['contact', 'visit_prompt.en', null, true],
            ['contact', 'whatsapp_message.ar', null, true],
            ['contact', 'whatsapp_message.en', null, true],

            // OFF, with no text. A response commitment is a promise about how
            // the company behaves, and code must never make one on its behalf
            // (§22.1). The client turns it on when they mean it.
            ['contact', 'response_promise_enabled', false, true],
            ['contact', 'response_promise.ar', null, true],
            ['contact', 'response_promise.en', null, true],

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

            // The sender's confirmation, written by the client (§6.2, §22.1).
            // Private: it is read on the server when the email is built.
            ['leads', 'confirmation.subject.ar', null, false],
            ['leads', 'confirmation.body.ar', null, false],
            ['leads', 'confirmation.subject.en', null, false],
            ['leads', 'confirmation.body.en', null, false],

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

        /*
         * `is_public` is structure and is corrected on every run; `value` is
         * the client's and is written once.
         *
         * The split matters. Once they have edited a heading in the panel, a
         * seeder must never overwrite it. But `is_public` decides whether a
         * setting is shared with the browser at all, it is not exposed in the
         * panel, and this list is the only place that knows the answer.
         *
         * This is the site that failed, and the reason SeedsRows exists —
         * the story is in its docblock.
         */
        foreach ($settings as [$group, $key, $value, $isPublic]) {
            $this->seedRow(
                Setting::query(),
                identity: ['group' => $group, 'key' => $key],
                structure: ['is_public' => $isPublic],
                owned: ['value' => $value],
            );
        }
    }
}
