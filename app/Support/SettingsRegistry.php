<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Describes every setting so the panel can present it to someone who does not
 * read code.
 *
 * The settings screen used to render the table straight out of the database:
 * one long page of rows labelled `dock_note.ar`, `map_embed_url`,
 * `organization_schema`. Every one of those is meaningful to a developer and
 * meaningless to the person who actually maintains the site. §9.1 requires the
 * panel to be usable "with no technical help needed", and a field nobody can
 * name is a field nobody will ever fill in.
 *
 * So this maps each key to a screen, a human name, a sentence explaining what
 * it does, and the kind of input it deserves. The names themselves are UI
 * strings and live in resources/lang (§22.5) — this file holds structure only.
 *
 * A key that is not listed here still appears, on the "advanced" screen, under
 * its raw name. Nothing is ever hidden from the client just because this file
 * has not caught up.
 */
class SettingsRegistry
{
    /** Screens, in sidebar order. Each is its own page. */
    public const SCREENS = ['contact', 'site', 'store', 'seo', 'tracking', 'robots', 'advanced'];

    /**
     * key => [screen, input type, sort order]
     *
     * Types: text · textarea · boolean · url · email · tel · code · list
     *
     * @return array<string, array{screen: string, type: string, order: int}>
     */
    public static function map(): array
    {
        return [
            // ---- Who you are -------------------------------------------
            'site.name.ar' => ['screen' => 'site', 'type' => 'text', 'order' => 10],
            'site.name.en' => ['screen' => 'site', 'type' => 'text', 'order' => 11],
            'site.copyright.ar' => ['screen' => 'site', 'type' => 'text', 'order' => 20],
            'site.copyright.en' => ['screen' => 'site', 'type' => 'text', 'order' => 21],
            'site.english_enabled' => ['screen' => 'site', 'type' => 'boolean', 'order' => 30],
            'site.contact_dock' => ['screen' => 'site', 'type' => 'boolean', 'order' => 31],

            // ---- How to reach you --------------------------------------
            'contact.email' => ['screen' => 'contact', 'type' => 'email', 'order' => 10],
            'contact.phone' => ['screen' => 'contact', 'type' => 'tel', 'order' => 11],
            'contact.whatsapp' => ['screen' => 'contact', 'type' => 'tel', 'order' => 12],
            'contact.address.ar' => ['screen' => 'contact', 'type' => 'textarea', 'order' => 20],
            'contact.address.en' => ['screen' => 'contact', 'type' => 'textarea', 'order' => 21],
            'contact.hours.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 30],
            'contact.hours.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 31],
            'contact.social' => ['screen' => 'contact', 'type' => 'list', 'order' => 40],
            'contact.map_query' => ['screen' => 'contact', 'type' => 'text', 'order' => 50],
            'contact.map_embed_url' => ['screen' => 'contact', 'type' => 'url', 'order' => 51],
            'contact.map_url' => ['screen' => 'contact', 'type' => 'url', 'order' => 52],
            'contact.dock_heading.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 60],
            'contact.dock_heading.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 61],
            'contact.dock_note.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 62],
            'contact.dock_note.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 63],
            // The button in the site header. Empty means no button — the
            // header must never invent a call to action (§22.1).
            'contact.header_cta.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 64],
            'contact.header_cta.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 65],
            // The short paragraph under the logo in the footer.
            'site.footer_blurb.ar' => ['screen' => 'site', 'type' => 'textarea', 'order' => 66],
            'site.footer_blurb.en' => ['screen' => 'site', 'type' => 'textarea', 'order' => 67],
            // The showroom block above the footer columns.
            'contact.location_heading.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 68],
            'contact.location_heading.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 69],
            'contact.location_note.ar' => ['screen' => 'contact', 'type' => 'textarea', 'order' => 70],
            'contact.location_note.en' => ['screen' => 'contact', 'type' => 'textarea', 'order' => 71],
            'contact.directions_label.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 72],
            'contact.directions_label.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 73],
            // The visit request on the contact page's location section: the
            // button's words, and the example put in the message box when it
            // is pressed. Empty label means no button (§22.1).
            'contact.visit_cta.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 74],
            'contact.visit_cta.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 75],
            'contact.visit_prompt.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 76],
            'contact.visit_prompt.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 77],
            // The message WhatsApp opens with, so the visitor never faces an
            // empty box and the sales team knows where the chat began.
            'contact.whatsapp_message.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 78],
            'contact.whatsapp_message.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 79],
            /*
             * The response commitment — "we reply within one working day".
             *
             * A promise on a public page that the company does not keep is
             * worse than no promise, so it ships OFF and its text is empty.
             * Both the switch and the words are the client's, and the panel's
             * own response-time figure is where they can check whether the
             * commitment is one they actually meet before turning it on.
             */
            'contact.response_promise_enabled' => ['screen' => 'contact', 'type' => 'boolean', 'order' => 80],
            'contact.response_promise.ar' => ['screen' => 'contact', 'type' => 'text', 'order' => 81],
            'contact.response_promise.en' => ['screen' => 'contact', 'type' => 'text', 'order' => 82],

            // ---- The Zid store -----------------------------------------
            'store.url' => ['screen' => 'store', 'type' => 'url', 'order' => 10],
            'store.label.ar' => ['screen' => 'store', 'type' => 'text', 'order' => 20],
            'store.label.en' => ['screen' => 'store', 'type' => 'text', 'order' => 21],

            // ---- Appearing in search ------------------------------------
            'seo.default_description.ar' => ['screen' => 'seo', 'type' => 'textarea', 'order' => 10],
            'seo.default_description.en' => ['screen' => 'seo', 'type' => 'textarea', 'order' => 11],
            'seo.default_og_image' => ['screen' => 'seo', 'type' => 'url', 'order' => 20],
            'seo.organization_schema' => ['screen' => 'advanced', 'type' => 'code', 'order' => 10],

            // ---- robots.txt, on a screen of its own ---------------------
            'seo.robots_txt' => ['screen' => 'robots', 'type' => 'code', 'order' => 10],

            // ---- Measurement --------------------------------------------
            'tracking.search_console' => ['screen' => 'tracking', 'type' => 'text', 'order' => 5],
            'tracking.gtm_id' => ['screen' => 'tracking', 'type' => 'text', 'order' => 10],
            'tracking.ga4_id' => ['screen' => 'tracking', 'type' => 'text', 'order' => 11],
            'tracking.meta_pixel_id' => ['screen' => 'tracking', 'type' => 'text', 'order' => 20],
            'tracking.meta_capi_token' => ['screen' => 'tracking', 'type' => 'text', 'order' => 21],
            'tracking.clarity_id' => ['screen' => 'tracking', 'type' => 'text', 'order' => 30],
        ];
    }

    /**
     * Everything the panel needs to draw one field.
     *
     * @return array{screen: string, type: string, order: int}
     */
    public static function describe(string $key): array
    {
        // Unknown keys land on "advanced" rather than vanishing. A setting
        // added by a later migration must never become invisible because this
        // list was not updated with it.
        return self::map()[$key] ?? ['screen' => 'advanced', 'type' => 'text', 'order' => 999];
    }

    /**
     * Example values and a shape check, for the fields where "did I paste the
     * right thing?" is a real question.
     *
     * Tracking IDs are the case that matters: they are opaque strings copied
     * out of another product's interface, and pasting the whole <script> tag
     * instead of the ID is the single most common way this goes wrong. A
     * pattern here lets the panel say "that does not look like a GTM ID" at
     * the moment of pasting, rather than leaving the client to discover weeks
     * later that nothing was ever measured.
     *
     * The check is advisory, never blocking: these formats are set by Google
     * and Meta, and a provider is free to change one tomorrow. Refusing to
     * save a value we merely fail to recognise would be worse than a warning.
     *
     * @return array<string, array{placeholder: string, pattern?: string}>
     */
    public static function hints(): array
    {
        return [
            'tracking.search_console' => [
                'placeholder' => 'AbC123dEf456GhI789jKl012MnO345pQr678StU90',
                // Search Console tokens are a long opaque slug. The frequent
                // mistake is pasting the entire meta tag, which this catches.
                'pattern' => '^[A-Za-z0-9_-]{20,}$',
            ],
            'tracking.gtm_id' => [
                'placeholder' => 'GTM-XXXXXXX',
                'pattern' => '^GTM-[A-Z0-9]{6,}$',
            ],
            'tracking.ga4_id' => [
                'placeholder' => 'G-XXXXXXXXXX',
                'pattern' => '^G-[A-Z0-9]{8,}$',
            ],
            'tracking.meta_pixel_id' => [
                'placeholder' => '123456789012345',
                'pattern' => '^[0-9]{10,20}$',
            ],
            'tracking.meta_capi_token' => [
                'placeholder' => 'EAAG...',
                'pattern' => '^[A-Za-z0-9_-]{20,}$',
            ],
            'tracking.clarity_id' => [
                'placeholder' => 'abcdefghij',
                'pattern' => '^[a-z0-9]{6,}$',
            ],
            'contact.email' => ['placeholder' => 'sales@amadcraft.sa'],
            'contact.phone' => ['placeholder' => '+966500000000'],
            'contact.whatsapp' => ['placeholder' => '+966500000000'],
            'store.url' => ['placeholder' => 'https://amadcraft.sa'],
            'contact.map_embed_url' => ['placeholder' => 'https://maps.google.com/maps?q=...&output=embed'],
            'seo.default_og_image' => ['placeholder' => '/images/share.jpg'],
        ];
    }

    public static function isScreen(string $screen): bool
    {
        return in_array($screen, self::SCREENS, true);
    }
}
