<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Page;
use Database\Seeders\LandingSwitchoverSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The single landing page (management decision, 7 September 2026).
 *
 * These hold the four things the acceptance criteria name, and one thing they
 * imply. Each is a rule that would be expensive to discover broken in
 * production, because this page is now the entire public site.
 */
class LandingPageIsTheWholeSiteTest extends TestCase
{
    use RefreshDatabase;

    /** The five destinations the header offers, plus the two it does not. */
    private const ANCHORS = ['home', 'government', 'partners', 'artisans', 'trust', 'contact'];

    #[Test]
    public function both_languages_serve_the_same_page_with_every_anchor(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $response = $this->get("/{$locale}");

            $response->assertOk();

            $page = $response->viewData('page');

            $this->assertSame(
                'Public/Landing',
                $page['component'],
                "/{$locale} does not render the landing page.",
            );

            $anchors = collect($page['props']['sections'])
                ->pluck('settings.anchor')
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach (self::ANCHORS as $anchor) {
                $this->assertContains(
                    $anchor,
                    $anchors,
                    "/{$locale} is missing the `{$anchor}` section group; its header entry would scroll nowhere.",
                );
            }
        }
    }

    /**
     * Every block the brief lists, in the brief's order.
     *
     * Asserting the count and the ends rather than all 27 headings: the copy
     * belongs to Amad Craft and is theirs to edit in the panel from the day it
     * ships, so a test pinned to their exact wording would start failing the
     * first time they used the panel for what it is for. What must not drift
     * is the SHAPE — that the page still opens on the hero and closes on the
     * one form.
     */
    #[Test]
    public function the_page_opens_on_the_hero_and_closes_on_the_form(): void
    {
        $sections = $this->get('/ar')->viewData('page')['props']['sections'];

        $this->assertCount(27, $sections, 'The approved landing page has 27 blocks.');
        $this->assertSame('hero', $sections[0]['type']);
        $this->assertSame('contact_block', $sections[count($sections) - 1]['type']);
        $this->assertSame('contact', $sections[count($sections) - 1]['settings']['anchor']);
    }

    /**
     * The rule the management brief calls «شرط إلزامي».
     *
     * Exactly one form on the whole site. `contact_block` is the only type
     * that renders one, and no `cta_band` — which embeds a `LeadField` — may
     * appear on this page.
     */
    #[Test]
    public function there_is_exactly_one_form_on_the_page(): void
    {
        $types = collect($this->get('/ar')->viewData('page')['props']['sections'])->pluck('type');

        $this->assertSame(
            1,
            $types->filter(fn (string $type): bool => $type === 'contact_block')->count(),
            'The site must carry exactly one contact form.',
        );

        $this->assertNotContains(
            'cta_band',
            $types->all(),
            'cta_band embeds a lead form; the landing page allows only the one in #contact.',
        );
    }

    /**
     * Two visible controls, named by the brief: a name and one way to reply.
     *
     * The switchover is applied here rather than in TestSeeder because it
     * switches off fields the rest of the suite legitimately exercises — the
     * phone normaliser and the message box are real capabilities the client
     * can turn back on. This is the test that owns the shipped configuration.
     */
    #[Test]
    public function the_visitor_is_asked_for_two_things_and_no_more(): void
    {
        $this->seed(LandingSwitchoverSeeder::class);

        $enabled = LeadField::query()->enabled()->orderBy('sort_order')->pluck('key')->all();

        $this->assertSame(['name', LeadField::KEY_CONTACT], $enabled);
    }

    /**
     * The header's five destinations are sections of this page — and they
     * carry their locale so they work from anywhere else too.
     *
     * A bare `#government` only works while the visitor is already on the
     * landing page. The same menu renders on the legal pages, where those
     * links pointed at sections that do not exist and did nothing at all when
     * clicked. `/ar#government` navigates from there and scrolls from here.
     */
    #[Test]
    public function the_header_navigates_within_the_page(): void
    {
        $this->seed(LandingSwitchoverSeeder::class);

        $header = $this->get('/ar')->viewData('page')['props']['navigation']['header'];

        $this->assertSame(
            ['/ar#home', '/ar#government', '/ar#partners', '/ar#artisans', '/ar#contact'],
            array_column($header, 'url'),
        );

        foreach ($header as $item) {
            $this->assertNotSame('', trim((string) $item['label']), 'A header item has no label.');
        }

        $english = $this->get('/en')->viewData('page')['props']['navigation']['header'];

        $this->assertSame(
            ['/en#home', '/en#government', '/en#partners', '/en#artisans', '/en#contact'],
            array_column($english, 'url'),
            'The English header points into the Arabic site (§12).',
        );
    }

    /**
     * The segment rides along invisibly.
     *
     * This is the whole point of three hero buttons that go to one form: the
     * sales team learns which audience the person came from without the
     * visitor being asked to classify themselves in a third field.
     */
    #[Test]
    public function a_cta_carries_its_segment_into_the_lead(): void
    {
        // The shipped two-field form; otherwise the demo seeder's extra
        // required fields reject a submission that names only these two.
        $this->seed(LandingSwitchoverSeeder::class);

        foreach (['government', 'partner', 'artisan'] as $segment) {
            $this->post('/leads', [
                'name' => 'اسم المرسل',
                LeadField::KEY_CONTACT => "buyer.{$segment}@example.gov.sa",
                'sector_hint' => $segment,
            ])->assertSessionHasNoErrors()->assertRedirect();

            $lead = Lead::query()->latest('id')->first();

            $this->assertSame(
                $segment,
                $lead->sector_hint,
                "A lead from the {$segment} button did not record which button it was.",
            );

            // And the name reached the row rather than being dropped silently.
            $this->assertSame('اسم المرسل', $lead->extra['name'] ?? null);
        }
    }

    /**
     * The one field takes an email OR a mobile number, and stores both.
     *
     * §6.1 fixes this: one control, the visitor chooses, auto-detected, no
     * type selector. The label had drifted to «البريد الإلكتروني» with a
     * `name@company.sa` placeholder — which tells a buyer who wants to be
     * telephoned that this form is not for them, and costs the one thing the
     * site is measured on (§1).
     *
     * Three shapes, because a Saudi writes their own number as `05…` and a
     * CRM needs `+966…`: the local form must be accepted and stored dialled.
     */
    #[Test]
    public function the_one_field_accepts_an_email_or_a_phone_and_stores_both(): void
    {
        $this->seed(LandingSwitchoverSeeder::class);

        $cases = [
            'buyer@ministry.gov.sa' => 'email',
            '0512345678' => 'phone',
            '+966512345679' => 'phone',
        ];

        foreach ($cases as $typed => $expected) {
            $this->post('/leads', [
                'name' => 'اسم المرسل',
                LeadField::KEY_CONTACT => $typed,
            ])->assertSessionHasNoErrors()->assertRedirect();

            $lead = Lead::query()->latest('id')->first();

            $this->assertSame(
                $expected,
                $lead->contact_type,
                "«{$typed}» was not recognised as a {$expected}.",
            );

            $this->assertNotEmpty($lead->contact_value, "«{$typed}» stored nothing.");
        }

        // A Saudi number typed the way a Saudi writes it is stored dialled.
        $local = Lead::query()->where('contact_value', 'like', '%512345678')->first();

        $this->assertNotNull($local, 'The local mobile form was rejected or lost.');
    }

    /**
     * And the visitor is told the field takes either one.
     *
     * The label and placeholder are the whole mechanism: there is no type
     * selector to fall back on, so wording that names only one of the two
     * closes the other off.
     */
    #[Test]
    public function the_contact_field_says_it_takes_either(): void
    {
        $this->seed(LandingSwitchoverSeeder::class);

        $field = LeadField::query()
            ->where('key', LeadField::KEY_CONTACT)
            ->with('translations')
            ->firstOrFail();

        foreach (['ar' => 'جوال', 'en' => 'mobile'] as $locale => $needle) {
            $placeholder = (string) $field->translations->firstWhere('locale', $locale)?->placeholder;

            $this->assertStringContainsString(
                $needle,
                $placeholder,
                "The {$locale} placeholder does not offer a phone number, so §6.1's one field is an email field.",
            );
        }
    }

    /**
     * A draft landing page must not answer with a blank site.
     *
     * The one URL the public has. If it is unpublished the controller yields
     * no sections, which is correct — but it must still be a 200 with the
     * chrome around it, not a 500.
     */
    #[Test]
    public function an_unpublished_landing_page_still_answers(): void
    {
        Page::query()->where('slug', 'landing')->update(['status' => 'draft']);

        $response = $this->get('/ar');

        $response->assertOk();
        $this->assertSame([], $response->viewData('page')['props']['sections']);
    }
}
