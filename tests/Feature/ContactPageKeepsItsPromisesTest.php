<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeadField;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /contact is where a decision that has already been made gets executed.
 *
 * Which makes one failure worse here than anywhere else: copy that describes a
 * form the visitor is not looking at. The page shipped saying «حقل واحد يكفي»
 * and «لا نطلب اسمًا» directly above a required company-name field — the first
 * page whose job is to earn trust, contradicting itself in one screen.
 *
 * These guard that, and the three other things this page must not do again:
 * state the address twice, print a contact value that is not the one in the
 * panel, and make a promise nobody at Amad Craft agreed to.
 */
class ContactPageKeepsItsPromisesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    // ---- The copy that described a form that no longer exists ---------- //

    /**
     * The single-field wording is gone from every piece of stored content.
     *
     * Scanned at the source rather than page by page: the same sentence lived
     * in the contact page's subtitle, in the campaign dock's note, in a
     * campaign's own block and in the privacy policy's list of what is
     * collected. A test that rendered one page would have cleared three of
     * those four while they were still live.
     */
    #[Test]
    public function no_stored_content_still_advertises_a_one_field_form(): void
    {
        $offences = [];

        foreach ($this->translationTables() as $table) {
            foreach (DB::table($table)->get() as $row) {
                foreach ((array) $row as $column => $value) {
                    if (! is_string($value)) {
                        continue;
                    }

                    foreach ($this->bannedPhrases($value) as $phrase) {
                        $offences[] = "{$table}.{$column}: {$phrase}";
                    }
                }
            }
        }

        foreach (Setting::query()->get() as $setting) {
            if (! is_string($setting->value)) {
                continue;
            }

            foreach ($this->bannedPhrases($setting->value) as $phrase) {
                $offences[] = "settings.{$setting->group}.{$setting->key}: {$phrase}";
            }
        }

        $this->assertSame([], $offences,
            'Content still promises the single-field form the company replaced with three required fields.');
    }

    /** And the same assertion from the visitor's end, on the page itself. */
    #[Test]
    public function the_contact_page_describes_the_form_it_actually_shows(): void
    {
        $text = $this->rendered($this->get('/ar/contact')->assertOk()->getContent());

        $this->assertSame([], $this->bannedPhrases($text));
        $this->assertStringContainsString('ثلاث خانات', $text,
            'The page no longer says how short the form is.');
    }

    // ---- One page, one location -------------------------------------- //

    /**
     * The page states where Amad Craft is exactly once — in the footer block.
     *
     * It used to carry its own «موقعنا» section AND the footer's site-wide
     * showroom block: two headings, two maps, two copies of the address and
     * two of the opening hours, inside a page that is two screens long.
     *
     * The first fix suppressed the footer block here. The client reversed it:
     * that block is the site's one answer to "where are you" and a visitor who
     * has learned to look for it at the foot of every page must find it at the
     * foot of this one. So the page-level section is what went, and this test
     * now asserts the surviving arrangement in both directions.
     */
    #[Test]
    public function the_contact_page_carries_exactly_one_location_section(): void
    {
        $text = $this->rendered($this->get('/ar/contact')->assertOk()->getContent());

        $this->assertStringContainsString(
            app(Settings::class)->get('contact.location_heading.ar'),
            $text,
            'The contact page lost the footer showroom block the rest of the site carries.',
        );

        $this->assertStringNotContainsString('موقعنا', $text,
            'The page-level location section is back, printing a second address.');
    }

    /** And it is the same block every other page shows. */
    #[Test]
    public function the_home_page_shows_the_same_showroom_block(): void
    {
        $text = $this->rendered($this->get('/ar')->assertOk()->getContent());

        $this->assertStringContainsString(
            app(Settings::class)->get('contact.location_heading.ar'),
            $text,
            'The showroom block has gone missing from the site.',
        );
    }

    // ---- One source for every contact value --------------------------- //

    /**
     * The card and the footer read the same row.
     *
     * Changing the number in the panel must change it in both, or the site
     * ships two phone numbers and the client has no way to know which one a
     * buyer dialled.
     */
    #[Test]
    public function changing_a_number_in_the_panel_changes_it_in_both_places(): void
    {
        // Through the model, not a mass update: the cache is busted by the
        // `saved` event, and a query-builder update fires none.
        $setting = Setting::query()->where('group', 'contact')->where('key', 'phone')->firstOrFail();
        $setting->value = '+966500000001';
        $setting->save();

        /*
         * Asserted on the `tel:` links, not on the whole response.
         *
         * The raw body also carries the Inertia prop JSON, which holds every
         * contact setting — including `contact.whatsapp`, a genuinely separate
         * field that this test does not change and that a business may well
         * point at a different number. Matching the bare string there failed
         * on a setting nobody had touched.
         *
         * What the assertion is actually about is what a visitor can dial, so
         * that is what it reads: two links, the card's and the footer's, both
         * carrying the new number and neither the old one.
         */
        $body = $this->get('/ar/contact')->assertOk()->getContent();

        preg_match_all('/href="tel:([^"]+)"/', $body, $matches);
        $dialable = $matches[1];

        $this->assertNotContains('+966553516589', $dialable,
            'The page still dials the number that was replaced in the panel.');
        $this->assertGreaterThanOrEqual(2, count(array_keys($dialable, '+966500000001', true)),
            'The new number reached only one of the card and the footer.');
    }

    /**
     * WhatsApp is a button that opens a chat already written.
     *
     * A printed number is a number to copy; a wa.me link with an opener in it
     * is a conversation. The opener is a setting, so it is never in the markup
     * except as what the client typed.
     */
    #[Test]
    public function the_whatsapp_button_opens_a_chat_that_is_already_written(): void
    {
        $settings = app(Settings::class);
        $digits = preg_replace('/\D/', '', (string) $settings->get('contact.whatsapp'));
        $opener = (string) $settings->get('contact.whatsapp_message.ar');

        $body = $this->get('/ar/contact')->assertOk()->getContent();

        $this->assertStringContainsString(
            "https://wa.me/{$digits}?text=".rawurlencode($opener),
            html_entity_decode($body, ENT_QUOTES),
            'The WhatsApp control is not a wa.me link carrying the opening message.',
        );
    }

    // ---- The form's shape --------------------------------------------- //

    /**
     * Three required fields and an optional message — decision A.
     *
     * The company fixed the three; §7's optional message was never revoked and
     * is what gives «أخبرونا كيف نخدمكم» something to receive. This asserts
     * both halves, because either drifting is a change to what the site
     * collects and what its privacy policy has to say.
     */
    #[Test]
    public function the_form_is_three_required_fields_plus_an_optional_message(): void
    {
        $enabled = LeadField::query()->enabled()->get();

        $this->assertSame(
            ['organisation', 'phone', 'contact'],
            $enabled->where('is_required', true)->sortBy('sort_order')->pluck('key')->values()->all(),
            'The three required fields the company approved have changed.',
        );

        $message = $enabled->firstWhere('key', LeadField::KEY_MESSAGE);

        $this->assertNotNull($message, 'The optional message field is switched off again.');
        $this->assertFalse((bool) $message->is_required, 'The message field became required.');
    }

    /**
     * The message box is open on arrival, with its label and «(اختياري)».
     *
     * This test asserted the opposite until the client reversed the decision:
     * the box was collapsed behind «أضف رسالة» to keep the form at three
     * controls (§10.6). The reasoning still holds for the required fields, but
     * a field nobody sees is a field nobody fills, and this is where a buyer
     * writes the one line worth qualifying on.
     *
     * The «(اختياري)» marker is asserted separately from the label because it
     * comes from the language file, not the database: it describes the field's
     * validation, and an editor renaming the label must not be able to make
     * the form claim something the server does not enforce.
     */
    #[Test]
    public function the_message_box_is_open_and_marked_optional(): void
    {
        $body = $this->get('/ar/contact')->assertOk()->getContent();

        $this->assertStringContainsString('lead-textarea', $body,
            'The optional message box is hidden again.');

        /*
         * The toggle is asserted by its CLASS, not by its label. Every
         * translation string is shipped to the browser in the i18n payload, so
         * «أضف رسالة» is present in the response whether or not anything
         * renders it — matching the words tested the language file, not the
         * page.
         */
        $this->assertStringNotContainsString('lead__toggle', $body,
            'The old "add a message" toggle is back.');
        $this->assertStringContainsString('(اختياري)', $body,
            'The message field does not say it is optional.');
    }

    /** Optional means optional: the form submits with the box left empty. */
    #[Test]
    public function the_form_submits_without_a_message(): void
    {
        $this->post('/ar/leads', [
            'organisation' => 'جهة اختبار',
            'phone' => '+966500000000',
            'contact' => 'test@example.com',
            'started_at' => now()->subSeconds(30)->timestamp,
        ])->assertSessionHasNoErrors();
    }

    // ---- Promises the client has not made ----------------------------- //

    #[Test]
    public function the_reply_time_commitment_stays_off_until_it_is_switched_on(): void
    {
        $text = $this->rendered($this->get('/ar/contact')->assertOk()->getContent());

        $this->assertStringNotContainsString('نرد خلال', $text,
            'The page is promising a reply time nobody switched on.');
    }

    #[Test]
    public function the_reply_time_commitment_appears_once_the_client_makes_it(): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'contact', 'key' => 'response_promise_enabled'],
            ['value' => true, 'is_public' => true]
        );
        Setting::query()->updateOrCreate(
            ['group' => 'contact', 'key' => 'response_promise.ar'],
            ['value' => 'نرد خلال يوم عمل واحد.', 'is_public' => true]
        );

        $text = $this->rendered($this->get('/ar/contact')->assertOk()->getContent());

        $this->assertStringContainsString('نرد خلال يوم عمل واحد.', $text);
    }

    /**
     * The visit request exists, and it is a request, not a second form.
     *
     * It sits in the direct-contact card now rather than in a location section
     * — that section is gone, and this is the one thing it owned which the
     * footer's showroom block does not offer.
     */
    #[Test]
    public function the_page_offers_an_appointment(): void
    {
        $label = (string) app(Settings::class)->get('contact.visit_cta.ar');
        $body = $this->get('/ar/contact')->assertOk()->getContent();

        $this->assertStringContainsString($label, $this->rendered($body));
        $this->assertSame(1, substr_count($body, '<form'),
            'The visit button grew a second form; §6.1 allows exactly one.');
    }

    // ------------------------------------------------------------------ //

    /**
     * The wording the company's decision made false, in both languages.
     *
     * Arabic is matched after stripping the tanween, so «لا نطلب اسمًا» and
     * «لا نطلب اسما» are the same offence.
     *
     * @return list<string>
     */
    private function bannedPhrases(string $haystack): array
    {
        $normalised = preg_replace('/[\x{064B}-\x{0652}]/u', '', $haystack) ?? $haystack;

        $banned = [
            'حقل واحد',
            'لا نطلب اسم',
            'One field is enough',
            'we ask for no name',
            'We ask for no name',
        ];

        return array_values(array_filter(
            $banned,
            fn (string $phrase): bool => str_contains($normalised, $phrase),
        ));
    }

    /** @return list<string> */
    private function translationTables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(),
            fn (string $table): bool => str_ends_with($table, '_translations'),
        ));
    }

    /** Reduced to what a visitor reads — props JSON, scripts and styles out. */
    private function rendered(string $html): string
    {
        $text = preg_replace('/data-page="[^"]*"/', '', $html) ?? '';
        $text = preg_replace('/<script[\s\S]*?<\/script>/', '', $text) ?? '';
        $text = preg_replace('/<style[\s\S]*?<\/style>/', '', $text) ?? '';

        return preg_replace('/<[^>]+>/', ' ', $text) ?? '';
    }
}
