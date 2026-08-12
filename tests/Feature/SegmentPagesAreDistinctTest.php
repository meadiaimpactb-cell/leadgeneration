<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Sector;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The four segment pages share a template, not their content.
 *
 * They exist because a procurement officer, a marketing manager, an events
 * agency and an artisan need to be addressed differently. Every time that has
 * slipped, it slipped the same way: a section seeded once and served four
 * times. These are the guards for the three that actually happened.
 */
class SegmentPagesAreDistinctTest extends TestCase
{
    use RefreshDatabase;

    private const SEGMENTS = ['government', 'companies', 'partners', 'artisans'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
        ]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /**
     * The hero's line must not be repeated as the paragraph beneath it.
     *
     * It was: the opening statement was seeded from the sector summary, which
     * is also the hero subtitle, so the same sentence appeared twice within
     * one screen — once small, once very large.
     */
    #[Test]
    public function no_segment_repeats_its_hero_line_in_the_body(): void
    {
        foreach (Sector::query()->get() as $sector) {
            $summary = (string) $sector->translations()->where('locale', 'ar')->value('summary');

            if ($summary === '') {
                continue;
            }

            $sections = $sector->sections()->with('translations')->get();

            foreach ($sections as $section) {
                $this->assertNotSame(
                    $summary,
                    (string) $section->translations()->where('locale', 'ar')->value('body'),
                    "The {$sector->slug} page repeats its hero line as a section body.",
                );
            }
        }
    }

    /**
     * "How we work" must not be one paragraph served four times.
     *
     * The steps differ by segment because the relationship does: a government
     * body is buying a procedure it can check, a partner has bought before
     * and is racing a tender deadline.
     */
    #[Test]
    public function the_process_steps_differ_between_segments(): void
    {
        $signatures = [];

        foreach (Sector::query()->get() as $sector) {
            $steps = $sector->sections()
                ->where('type', 'process_steps')
                ->value('settings');

            $this->assertNotEmpty($steps['items'] ?? [], "{$sector->slug} has no process steps.");

            $signatures[$sector->slug] = array_column($steps['items'], 'title');
        }

        $this->assertNotSame(
            $signatures['government'] ?? null,
            $signatures['partners'] ?? null,
            'The partner page runs the government page\'s procedure.',
        );
    }

    /** Each segment carries at least one section the others do not. */
    #[Test]
    public function each_segment_carries_a_section_of_its_own(): void
    {
        $headings = [];

        foreach (Sector::query()->get() as $sector) {
            $headings[$sector->slug] = $sector->sections()
                ->with('translations')
                ->get()
                ->map(fn ($s): ?string => $s->translations->firstWhere('locale', 'ar')?->heading)
                ->filter()
                ->all();
        }

        // Companies own their occasions; partners own their pledge.
        $this->assertContains('متى تطلب الشركات منّا', $headings['companies'] ?? []);
        $this->assertNotContains('متى تطلب الشركات منّا', $headings['government'] ?? []);

        $this->assertContains('تعهّدنا لشركائنا', $headings['partners'] ?? []);
        $this->assertNotContains('تعهّدنا لشركائنا', $headings['companies'] ?? []);
    }

    /**
     * No figure that reads as a price, a margin or a commission rate.
     *
     * The partner page talks about pricing being clear and about a referral
     * model; §2.2 forbids the site carrying either as a number. Both must
     * stay sentences that hand off to the sales team.
     */
    #[Test]
    public function the_partner_page_quotes_no_rate(): void
    {
        $body = $this->get('/ar/solutions/partners')->assertOk()->getContent();

        /*
         * Reduced to what a visitor actually reads, in three steps, each of
         * which caught a false positive:
         *
         *   the Inertia data-page JSON  — carries every URL on the page
         *   <script> and <style> bodies — stripping tags leaves what is
         *                                 BETWEEN them, and the map embed's
         *                                 `%3A` lives in a script body
         *   the remaining tags          — everything else
         */
        $text = preg_replace('/data-page="[^"]*"/', '', $body) ?? '';
        $text = preg_replace('/<script[\s\S]*?<\/script>/', '', $text) ?? '';
        $text = preg_replace('/<style[\s\S]*?<\/style>/', '', $text) ?? '';
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? '';

        foreach (['ر.س', 'SAR', 'ريال'] as $currency) {
            $this->assertStringNotContainsString($currency, $text,
                'The partner page names a currency — §2.2 allows no figure here.');
        }

        $this->assertDoesNotMatchRegularExpression('/\d+\s*%/u', $text,
            'The partner page quotes a percentage — a rate on a public page is a price list.');
    }

    /**
     * The form's shape is fixed; its wording is not.
     *
     * §6.1 fixes three fields and one endpoint everywhere. The artisan page
     * relabels the first — "اسم الشركة" is wrong for an individual — and this
     * asserts the relabel happened AND that nothing else about the form did.
     */
    #[Test]
    public function the_artisan_page_relabels_the_first_field_without_changing_it(): void
    {
        $body = $this->get('/ar/solutions/artisans')->assertOk()->getContent();

        $this->assertStringContainsString('الاسم أو اسم المشروع الحرفي', $body);

        /*
         * Counted by matching the class as a TOKEN, not the attribute
         * verbatim. The email and phone inputs also carry `lead-input--mono`,
         * so Vue merges them into `class="lead-input lead-input--mono"` and
         * an exact-string count sees two fields where there are three. This
         * is the third time a bound class has broken a verbatim match; see
         * DESIGN_SYSTEM.md.
         */
        $this->assertSame(3, preg_match_all('/<input[^>]*class="[^"]*\blead-input\b/', $body),
            'The artisan page does not carry the same three fields.');
        $this->assertStringContainsString('name="contact"', $body);
        $this->assertStringContainsString('autocomplete="email tel"', $body);
    }

    /**
     * The artisan is the one segment that is not buying. A section addressed
     * to a buyer has no business on their page — the buyer-facing services
     * grid was there, and it was the loudest wrong note on the site.
     */
    #[Test]
    public function the_artisan_page_offers_nothing_addressed_to_a_buyer(): void
    {
        $body = $this->get('/ar/solutions/artisans')->assertOk()->getContent();

        // Rendered text only — the page's prop JSON is checked separately.
        $text = preg_replace('/data-page="[^"]*"/', '', $body) ?? '';
        $text = preg_replace('/<script[\s\S]*?<\/script>/', '', $text) ?? '';

        foreach (['الهدايا المؤسسية', 'مستلزمات الفعاليات', 'الإنتاج المخصص'] as $buyerService) {
            $this->assertStringNotContainsString($buyerService, $text,
                "The artisan page offers «{$buyerService}» — a service for someone who is not reading it.");
        }
    }

    #[Test]
    public function every_segment_still_renders_in_both_locales(): void
    {
        foreach (self::SEGMENTS as $segment) {
            $this->get("/ar/solutions/{$segment}")->assertOk();
        }
    }

    /** One render per test — see the SSR note in HANDOFF.md. */
    #[Test]
    public function the_english_partner_page_renders(): void
    {
        $this->get('/en/solutions/partners')->assertOk();
    }

    /**
     * A hero action does not send the reader off a page that already asks.
     *
     * All four heroes pointed at /contact while the page they sat on ended in
     * the very form /contact would have shown — carrying `sectorHint`, which
     * /contact cannot know. The visitor most likely to convert was the one
     * being moved. Both buttons now land on `#lead`.
     *
     * The anchor is asserted to EXIST as well as to be pointed at: a CTA
     * aimed at a section the client has switched off in the panel is a button
     * that does nothing, which is worse than one that navigates.
     */
    #[Test]
    public function both_hero_actions_land_on_the_form_the_page_already_carries(): void
    {
        foreach (self::SEGMENTS as $segment) {
            $body = $this->get("/ar/solutions/{$segment}")->assertOk()->getContent();

            $this->assertStringContainsString('id="lead"', $body,
                "The {$segment} page has no form for its hero to point at.");

            preg_match('/class="shero__actions"[^>]*>(.*?)<\/div>/s', $body, $actions);

            $this->assertNotEmpty($actions, "The {$segment} hero renders no actions.");

            preg_match_all('/href="([^"]*)"/', $actions[1], $hrefs);

            $this->assertSame(['#lead', '#lead'], $hrefs[1],
                "A {$segment} hero button leaves a page that carries its own form.");
        }
    }
}
