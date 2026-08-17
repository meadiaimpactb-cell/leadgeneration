<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpactMetric;
use App\Models\Page;
use App\Models\Report;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /impact is the page every other page points at.
 *
 * A government buyer copies these figures into their own reporting, which
 * makes one rule stricter here than anywhere else on the site: nothing may
 * claim more than it can prove. These guard the three ways that failed.
 */
class ImpactPageProvesItsClaimsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /**
     * No page renders a hero without its heading.
     *
     * /impact shipped as two buttons on an empty navy field. The cause was a
     * silent name collision, not missing data: this page has a prop called
     * `page`, and a top-level `const page = usePage()` in <script setup> wins
     * over the prop in the template, so `page?.title` resolved to the Inertia
     * page object. Nothing threw. The `v-if` simply saw undefined.
     *
     * Asserted across every hero page, because the same collision is available
     * to any of them and no framework error announces it.
     */
    #[Test]
    public function no_page_shows_a_hero_without_a_heading(): void
    {
        foreach (['/ar/impact', '/ar/about', '/ar/training', '/ar/contact'] as $url) {
            $body = $this->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<h1[^>]*>\s*\S/', $body,
                "{$url} renders a hero with no heading in it.");
        }
    }

    /**
     * One record, read twice — never two stored copies of one figure.
     *
     * The home page and /impact both show the artisan count. If either ever
     * gets its own copy, they will disagree the first time the client edits
     * one, and the number a public body has already published becomes wrong.
     */
    #[Test]
    public function editing_a_figure_in_one_place_changes_it_everywhere(): void
    {
        ImpactMetric::query()->where('key', 'artisans')->update(['value_numeric' => 617]);

        $impact = $this->get('/ar/impact')->assertOk()->getContent();

        $this->assertStringContainsString('617', $impact,
            'The impact page does not read the artisan figure from the record.');
        $this->assertStringNotContainsString('240', $impact,
            'The impact page still carries the previous artisan figure.');
    }

    /** The same assertion from the other end. One render per test — SSR. */
    #[Test]
    public function the_home_page_reads_the_same_record(): void
    {
        ImpactMetric::query()->where('key', 'artisans')->update(['value_numeric' => 617]);

        $home = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('617', $home,
            'The home page does not read the artisan figure from the same record.');
    }

    /**
     * No download button for a report with no file.
     *
     * There were two, both offering a 612-byte PDF whose size was printed on
     * the button. A dead download on the credibility page costs more than a
     * missing one.
     */
    #[Test]
    public function a_report_without_a_file_offers_no_download(): void
    {
        $this->assertTrue(
            Report::query()->get()->every(fn (Report $r): bool => $r->getMedia('file')->isEmpty()),
            'A seeder is attaching a stand-in file to a report again.',
        );

        $body = $this->get('/ar/impact')->assertOk()->getContent();
        $text = $this->rendered($body);

        $this->assertStringContainsString('التقرير قيد الإعداد', $text);
        $this->assertStringNotContainsString('تحميل التقرير', $text,
            'A report with no file is still offering a download.');
    }

    /** With a file attached, the download returns and carries its format. */
    #[Test]
    public function a_report_with_a_file_offers_one(): void
    {
        $report = Report::query()->firstOrFail();
        $report->addMedia(UploadedFile::fake()->create('impact.pdf', 400))
            ->toMediaCollection('file');

        $text = $this->rendered($this->get('/ar/impact')->assertOk()->getContent());

        $this->assertStringContainsString('تحميل التقرير', $text);
        $this->assertStringContainsString('PDF', $text,
            'The download does not say what format it hands over.');
    }

    /**
     * The year is stated once, by the field that holds it.
     *
     * A card headed «تقرير الأثر السنوي 2025» wearing a 2023 badge is what two
     * fields for one fact produces.
     */
    #[Test]
    public function the_year_is_not_typed_into_the_report_title(): void
    {
        foreach (Report::query()->with('translations')->get() as $report) {
            foreach ($report->translations as $translation) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\d{4}/',
                    (string) $translation->title,
                    "Report {$report->slug} repeats its year inside the title.",
                );
            }
        }
    }

    /**
     * A story is illustrated by a person or by nothing — never by the product.
     *
     * The section exists to put an artisan in front of the reader. It was
     * showing photographs of merchandise on a white sweep, so a story about a
     * grandmother's loom arrived illustrated with a blue notebook.
     */
    #[Test]
    public function no_story_is_illustrated_with_a_product_photograph(): void
    {
        foreach (Story::query()->with('media')->get() as $story) {
            $file = $story->getFirstMedia('person')?->file_name;

            $this->assertFalse(
                $file !== null && str_starts_with($file, 'craft-'),
                "Story {$story->slug} is illustrated with a catalogue photograph.",
            );
        }
    }

    /**
     * Every story is illustrated, and by a photograph of people at work.
     *
     * Amad Craft supplied three from their own workshop — hands at the loom,
     * palm fronds being split at the bench. Asserted rather than assumed
     * because the failure mode is silent: `attachImage` returns early when the
     * collection is occupied, so a stale catalogue shot keeps its place and
     * the page looks finished while showing the wrong thing.
     */
    #[Test]
    public function every_story_carries_a_photograph_of_someone_working(): void
    {
        foreach (Story::query()->visible()->with('media')->get() as $story) {
            $media = $story->getFirstMedia('person');

            $this->assertNotNull($media, "Story {$story->slug} lost its photograph.");

            $this->assertNotSame(
                'قطعة حرفية من إنتاج أمد الحرف',
                $media->translations()->where('locale', 'ar')->value('alt_text'),
                "Story {$story->slug} describes its photograph as a product.",
            );
        }
    }

    /** «كل القصص» appears only when there are more than the three on show. */
    #[Test]
    public function the_all_stories_link_stays_away_until_there_are_more(): void
    {
        $text = $this->rendered($this->get('/ar/impact')->assertOk()->getContent());

        $this->assertSame(3, Story::query()->visible()->count());
        $this->assertStringNotContainsString('كل القصص', $text,
            'The page offers "all stories" while showing all of them.');
    }

    #[Test]
    public function every_published_story_has_a_page_of_its_own(): void
    {
        $story = Story::query()->visible()->firstOrFail();

        $this->get("/ar/impact/stories/{$story->slug}")->assertOk();
    }

    /**
     * A section with nothing in it renders nothing.
     *
     * «كيف نقيس» and the Vision 2030 band are seeded as empty containers,
     * because their content is methodology only Amad Craft can state. This
     * asserts the empty state is silent rather than a heading over a void.
     */
    #[Test]
    public function an_unfilled_section_publishes_no_heading(): void
    {
        $text = $this->rendered($this->get('/ar/impact')->assertOk()->getContent());

        $this->assertStringNotContainsString('كيف نقيس', $text);
        $this->assertStringNotContainsString('ارتباطنا برؤية', $text);
    }

    /**
     * Reduced to what a visitor reads: the Inertia prop JSON, then script and
     * style bodies, then the tags. Stripping tags alone leaves what is
     * *between* them — the lesson `SegmentPagesAreDistinctTest` paid for.
     */
    private function rendered(string $html): string
    {
        $text = preg_replace('/data-page="[^"]*"/', '', $html) ?? '';
        $text = preg_replace('/<script[\s\S]*?<\/script>/', '', $text) ?? '';
        $text = preg_replace('/<style[\s\S]*?<\/style>/', '', $text) ?? '';

        return preg_replace('/<[^>]+>/', ' ', $text) ?? '';
    }
}
