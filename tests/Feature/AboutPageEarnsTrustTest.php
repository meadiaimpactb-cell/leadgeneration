<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpactMetric;
use App\Models\Page;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /about is the last stop before the decision.
 *
 * An institutional visitor arrives here already convinced of the value and
 * asks one question: is this a serious organisation I would put my employer's
 * name beside? So it is a page of story and proof, and its failure mode is
 * being a second copy of the pages that did the convincing.
 */
class AboutPageEarnsTrustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /**
     * The bridge diagram is the page's signature and its only visual
     * explanation of the business model.
     *
     * Asserted with its accessible description, not just its presence: an SVG
     * carrying the whole model and no text alternative explains it to everyone
     * except the visitor who most needs it explained.
     */
    #[Test]
    public function the_bridge_diagram_is_drawn_and_described(): void
    {
        $body = $this->get('/ar/about')->assertOk()->getContent();

        $this->assertStringContainsString('bridge', $body,
            'The bridge model is missing from the page it was built for.');
        $this->assertMatchesRegularExpression('/role="img"|<desc/', $body,
            'The bridge diagram carries no text alternative.');
    }

    /**
     * The five stages come from the panel, not from the template.
     *
     * They are the operational spine of the model, and §22.1 puts every word a
     * marketer would have an opinion on in the database.
     */
    #[Test]
    public function the_bridge_stages_are_managed_from_the_panel(): void
    {
        $settings = Page::query()->where('slug', 'about')->firstOrFail()
            ->sections()->where('type', 'bridge_model')->value('settings');

        $this->assertNotEmpty($settings['items'] ?? [],
            'The bridge diagram has no stages to draw.');
    }

    /**
     * It must not repeat the segment pages.
     *
     * The page used to carry «كيف نعمل» — the same operational procedure that
     * appears on all four solution pages. Here the question is not "how will
     * you deliver my order", it is "what is your model", and answering the
     * first was the reason the page felt like a fifth segment.
     */
    #[Test]
    public function the_about_page_does_not_repeat_the_segments(): void
    {
        /*
         * The header and the footer come off first.
         *
         * The artisan page's opening step is «تواصلوا معنا», which is also a
         * navigation label on every page on the site. Matched against the
         * whole document this reported the menu as a duplicated procedure —
         * a false positive that would have taught the next person to distrust
         * the test rather than the page.
         */
        $body = $this->get('/ar/about')->assertOk()->getContent();
        $body = preg_replace('/<header[\s\S]*?<\/header>/', '', $body) ?? $body;
        $body = preg_replace('/<footer[\s\S]*?<\/footer>/', '', $body) ?? $body;

        $text = $this->rendered($body);

        foreach (Sector::query()->get() as $sector) {
            $steps = $sector->sections()->where('type', 'process_steps')->value('settings');

            foreach (array_column($steps['items'] ?? [], 'title') as $step) {
                $this->assertStringNotContainsString((string) $step, $text,
                    "The about page repeats the {$sector->slug} page's procedure.");
            }
        }
    }

    /**
     * The figures here are the same record the home page and /impact read.
     *
     * A fourth copy of the artisan count is a fourth chance for it to be
     * wrong, and this is the page where being checkable is the whole point.
     */
    #[Test]
    public function the_figures_come_from_the_one_record(): void
    {
        ImpactMetric::query()->where('key', 'artisans')->update(['value_numeric' => 617]);

        $body = $this->get('/ar/about')->assertOk()->getContent();

        $this->assertStringContainsString('617', $body,
            'The about page does not read the figures from the shared record.');

        /*
         * The stale figure must not be RENDERED — which is not the same as it
         * being absent from the document.
         *
         * This asserted `240` appeared nowhere in the HTML at all, and the
         * page carries strings nobody controls: the CSRF token, and Inertia's
         * asset version, which is an md5 of the build manifest. The build that
         * shipped the dashboard fix produced version
         * e182e5be34a16b625029fd52409a61d1 — `…fd52409a61d1` contains `240`,
         * and this test began failing on every run for a reason that had
         * nothing to do with impact figures. It was a coin toss on every
         * `npm run build`.
         *
         * A figure reaches the page as the text of its own element, so that is
         * what is checked.
         */
        $this->assertStringNotContainsString('>240<', $body,
            'The about page carries its own stale copy of the artisan figure.');
    }

    /**
     * The team grid stays off until Amad Craft decides to publish people.
     *
     * A grid of invented names and stock portraits on the trust page would be
     * the worst placeholder on the site, so the default is off (§22.1).
     */
    #[Test]
    public function the_team_section_is_off_until_it_is_switched_on(): void
    {
        $text = $this->rendered($this->get('/ar/about')->assertOk()->getContent());

        $this->assertStringNotContainsString('فريق العمل', $text,
            'The team section is publishing before anyone supplied a team.');
    }

    /**
     * Its photographs are of the place, not of the merchandise the segment
     * pages already show.
     */
    #[Test]
    public function the_about_page_shares_no_photograph_with_a_segment_page(): void
    {
        $body = $this->get('/ar/about')->assertOk()->getContent();

        preg_match_all('/<img[^>]*src="([^"]+)"/', $body, $matches);
        $here = array_map(static fn (string $u): string => basename($u), $matches[1]);

        foreach (['1.avif', '4.avif', '6.jpeg', '8.jpeg'] as $segmentHero) {
            $this->assertNotContains($segmentHero, $here,
                'The about page reuses a segment page\'s hero photograph.');
        }
    }

    /** Reduced to what a visitor reads — see `SegmentPagesAreDistinctTest`. */
    private function rendered(string $html): string
    {
        $text = preg_replace('/data-page="[^"]*"/', '', $html) ?? '';
        $text = preg_replace('/<script[\s\S]*?<\/script>/', '', $text) ?? '';
        $text = preg_replace('/<style[\s\S]*?<\/style>/', '', $text) ?? '';

        return preg_replace('/<[^>]+>/', ' ', $text) ?? '';
    }
}
