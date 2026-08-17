<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageKeyword;
use App\Models\User;
use App\Services\Seo\KeywordAnalyzer;
use App\Services\Seo\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Per-page keyword analysis (§13).
 *
 * Three things decide whether this feature is worth having, and each has its
 * own group below:
 *
 *   · the Arabic normalisation, without which the tool reports words the page
 *     visibly contains as missing and nobody believes any of it again
 *   · the arithmetic, so a colour means the same thing every time
 *   · the automatic re-analysis, because a stale score is worse than no score
 */
class PageKeywordAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->create([
            'name' => 'SEO admin',
            'email' => 'pk@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);

        $this->page = Page::query()->create([
            'slug' => 'corporate-gifts',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);
    }

    private function analyzer(): KeywordAnalyzer
    {
        return app(KeywordAnalyzer::class);
    }

    private function normalizer(): TextNormalizer
    {
        return app(TextNormalizer::class);
    }

    /** @param  array<string, mixed>  $fields */
    private function translate(array $fields, string $locale = 'ar'): void
    {
        $this->page->translations()->updateOrCreate(['locale' => $locale], $fields);
        $this->page->refresh();
    }

    // ------------------------------------------------------------------ //
    // Normalisation
    // ------------------------------------------------------------------ //

    #[Test]
    public function hamza_spelling_does_not_change_the_match(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame(
            $normalizer->normalise('إهداء أمد'),
            $normalizer->normalise('اهداء امد'),
        );
    }

    #[Test]
    public function diacritics_are_ignored(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame(
            $normalizer->normalise('الحِرفة السعوديّة'),
            $normalizer->normalise('الحرفة السعودية'),
        );
    }

    #[Test]
    public function taa_marbuta_and_alef_maqsura_are_unified(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame($normalizer->normalise('هدية'), $normalizer->normalise('هديه'));
        $this->assertSame($normalizer->normalise('مقهى'), $normalizer->normalise('مقهي'));
    }

    #[Test]
    public function tatweel_and_arabic_digits_are_folded(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame($normalizer->normalise('حــرف'), $normalizer->normalise('حرف'));
        $this->assertSame($normalizer->normalise('رؤية ٢٠٣٠'), $normalizer->normalise('رؤية 2030'));
    }

    #[Test]
    public function english_is_lowercased_and_trimmed(): void
    {
        $this->assertSame('corporate gifts', $this->normalizer()->normalise('  Corporate  GIFTS ', 'en'));
    }

    /**
     * Punctuation separates rather than disappears.
     *
     * Both halves matter: a phrase followed by a comma must still match, and
     * a hyphenated pair must not fuse into a word that matches neither side.
     */
    #[Test]
    public function punctuation_becomes_a_space_and_not_nothing(): void
    {
        $normalizer = $this->normalizer();

        $this->assertSame($normalizer->normalise('الحرف'), $normalizer->normalise('الحرف،'));
        $this->assertSame('حرف يدوي', $normalizer->normalise('حرف-يدوي'));
    }

    /** Density counts words, so the phrase and the page use the same unit. */
    #[Test]
    public function words_are_counted_after_folding(): void
    {
        $this->assertSame(0, $this->normalizer()->wordCount(''));
        $this->assertSame(3, $this->normalizer()->wordCount('هدايا مؤسسية حكومية'));
    }

    /** The point of all of the above, end to end. */
    #[Test]
    public function a_differently_spelled_phrase_still_matches_the_page(): void
    {
        $this->translate(['title' => 'الحرفة السعودية', 'meta_title' => 'الحرفة السعودية']);

        $result = $this->analyzer()->analyse($this->page, 'ar', 'الحرفه السعوديه');

        $this->assertTrue($result['checks']['heading']['passed'],
            'A taa marbuta spelled as a haa failed to match — the normalisation is not reaching both sides.');
    }

    // ------------------------------------------------------------------ //
    // The arithmetic
    // ------------------------------------------------------------------ //

    #[Test]
    public function the_weights_add_up_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(KeywordAnalyzer::WEIGHTS));
    }

    #[Test]
    public function a_phrase_the_page_never_uses_scores_only_the_credited_slug(): void
    {
        $this->translate(['title' => 'من نحن', 'meta_title' => 'من نحن']);

        $result = $this->analyzer()->analyse($this->page, 'ar', 'تحكيم جودة النخيل');

        // 5 is the Arabic slug credit and nothing else.
        $this->assertSame(KeywordAnalyzer::WEIGHTS['slug'], $result['score']);
    }

    #[Test]
    public function each_field_adds_its_own_weight(): void
    {
        $term = 'هدايا مؤسسية';
        $w = KeywordAnalyzer::WEIGHTS;

        $this->translate(['title' => 'من نحن']);
        $base = $this->analyzer()->analyse($this->page, 'ar', $term)['score'];
        $this->assertSame($w['slug'], $base);

        $this->translate(['title' => 'من نحن', 'meta_title' => $term]);
        $this->assertSame($base + $w['meta_title'], $this->analyzer()->analyse($this->page, 'ar', $term)['score']);

        $this->translate(['title' => $term, 'meta_title' => $term]);
        $this->assertSame(
            $base + $w['meta_title'] + $w['heading'],
            $this->analyzer()->analyse($this->page, 'ar', $term)['score'],
        );

        $this->translate(['title' => $term, 'meta_title' => $term, 'meta_description' => $term]);
        $this->assertSame(
            $base + $w['meta_title'] + $w['heading'] + $w['meta_description'],
            $this->analyzer()->analyse($this->page, 'ar', $term)['score'],
        );
    }

    /** One mention scores once; a second mention scores the pair. */
    #[Test]
    public function the_second_mention_is_worth_its_own_weight(): void
    {
        $term = 'دروع تكريم';
        $w = KeywordAnalyzer::WEIGHTS;

        $this->translate(['title' => 'من نحن', 'excerpt' => "نصنع {$term} للجهات."]);
        $once = $this->analyzer()->analyse($this->page, 'ar', $term);

        $this->assertSame(1, $once['occurrences']);
        $this->assertSame($w['slug'] + $w['in_body_once'] + $w['first_paragraph'], $once['score']);

        $this->translate(['title' => 'من نحن', 'excerpt' => "نصنع {$term} للجهات. {$term} بمواصفات."]);
        $twice = $this->analyzer()->analyse($this->page, 'ar', $term);

        $this->assertSame(2, $twice['occurrences']);
        $this->assertSame($once['score'] + $w['in_body_twice'], $twice['score']);
    }

    #[Test]
    public function the_slug_is_credited_in_arabic_and_checked_in_english(): void
    {
        $this->translate(['title' => 'x'], 'ar');
        $this->translate(['title' => 'x'], 'en');

        // The page's slug is "corporate-gifts".
        $this->assertTrue(
            $this->analyzer()->analyse($this->page, 'ar', 'أي شيء')['checks']['slug']['passed'],
            'An Arabic keyword must not be marked down for a slug it can never appear in.',
        );

        $this->assertTrue($this->analyzer()->analyse($this->page, 'en', 'corporate gifts')['checks']['slug']['passed']);
        $this->assertFalse($this->analyzer()->analyse($this->page, 'en', 'palm weaving')['checks']['slug']['passed']);
    }

    // ------------------------------------------------------------------ //
    // The three bands
    // ------------------------------------------------------------------ //

    #[Test]
    public function the_band_boundaries_are_where_the_panel_says_they_are(): void
    {
        $cases = [
            0 => 'weak',
            39 => 'weak',
            40 => 'medium',
            69 => 'medium',
            70 => 'strong',
            100 => 'strong',
        ];

        foreach ($cases as $score => $band) {
            $keyword = new PageKeyword(['score' => $score]);

            $this->assertSame($band, $keyword->band(), "A score of {$score} landed in the wrong colour.");
        }
    }

    // ------------------------------------------------------------------ //
    // Content assembly
    // ------------------------------------------------------------------ //

    #[Test]
    public function only_this_locale_s_copy_is_searched(): void
    {
        $this->translate(['title' => 'الحرفة السعودية'], 'ar');
        $this->translate(['title' => 'Saudi handcraft'], 'en');

        $this->assertFalse(
            $this->analyzer()->analyse($this->page, 'en', 'الحرفة السعودية')['checks']['heading']['passed'],
            'Arabic copy is being counted towards an English keyword.',
        );

        $this->assertFalse(
            $this->analyzer()->analyse($this->page, 'ar', 'saudi handcraft')['checks']['heading']['passed'],
        );
    }

    #[Test]
    public function copy_inside_a_section_counts_and_a_url_does_not(): void
    {
        $this->translate(['title' => 'من نحن']);

        $section = $this->page->sections()->create([
            'type' => 'cards',
            'sort_order' => 0,
            'is_active' => true,
            'settings' => [
                'items' => [
                    ['title' => 'دروع تكريم', 'body' => 'قطع حرفية للمناسبات.', 'title_en' => 'Awards'],
                ],
                // A URL that contains the phrase must not score: nobody reads it.
                'ctaUrl' => '/ar/دروع-تكريم',
            ],
        ]);

        $section->translations()->create(['locale' => 'ar', 'heading' => 'ما نقدّمه']);

        $this->page->refresh();

        $result = $this->analyzer()->analyse($this->page, 'ar', 'دروع تكريم');

        $this->assertTrue($result['checks']['in_body_once']['passed'], 'Card copy is not being read.');
        $this->assertSame(1, $result['occurrences'],
            'The phrase was counted twice — the URL in settings is being scored as prose.');

        // And the English card title must not reach the Arabic haystack.
        $this->assertFalse($this->analyzer()->analyse($this->page, 'ar', 'awards')['checks']['in_body_once']['passed']);
    }

    #[Test]
    public function an_inactive_section_is_not_counted(): void
    {
        $this->translate(['title' => 'من نحن']);

        $section = $this->page->sections()->create([
            'type' => 'rich_text', 'sort_order' => 0, 'is_active' => false,
        ]);
        $section->translations()->create(['locale' => 'ar', 'body' => '<p>سعف النخيل</p>']);

        $this->page->refresh();

        $this->assertFalse(
            $this->analyzer()->analyse($this->page, 'ar', 'سعف النخيل')['checks']['in_body_once']['passed'],
            'A switched-off section is scoring for words no visitor can read.',
        );
    }

    /**
     * The check the whole `section_items` migration exists for.
     *
     * Cards, steps and questions are moving out of `settings->items[]` into
     * rows of their own. A phrase living only in one of those rows and scored
     * as absent would be the worst kind of wrong: the editor is looking at the
     * words on the page while the screen says they are missing.
     */
    #[Test]
    public function copy_that_lives_only_in_a_section_item_still_counts(): void
    {
        $this->translate(['title' => 'من نحن']);

        $section = $this->page->sections()->create([
            'type' => 'cards', 'sort_order' => 0, 'is_active' => true,
        ]);
        $section->translations()->create(['locale' => 'ar', 'heading' => 'ما نقدّمه']);

        $item = $section->items()->create(['sort_order' => 0, 'is_active' => true]);
        $item->translations()->create([
            'locale' => 'ar',
            'title' => 'تذكارات المؤتمرات',
            'body' => 'قطع حرفية تُسلَّم بالتاريخ المتفق عليه.',
            // Never prose: an address containing the phrase is not the page
            // saying it.
            'cta_url' => '/ar/تذكارات-المؤتمرات',
        ]);

        $this->page->refresh();

        $result = $this->analyzer()->analyse($this->page, 'ar', 'تذكارات المؤتمرات');

        $this->assertTrue($result['checks']['in_body_once']['passed'],
            'A card stored as a section_item row is not being read into the body.');
        $this->assertSame(1, $result['occurrences'],
            'The phrase was counted twice — the item CTA URL is being scored as prose.');
    }

    #[Test]
    public function an_item_switched_off_is_not_counted(): void
    {
        $this->translate(['title' => 'من نحن']);

        $section = $this->page->sections()->create([
            'type' => 'cards', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = $section->items()->create(['sort_order' => 0, 'is_active' => false]);
        $item->translations()->create(['locale' => 'ar', 'title' => 'سعف النخيل']);

        $this->page->refresh();

        $this->assertFalse(
            $this->analyzer()->analyse($this->page, 'ar', 'سعف النخيل')['checks']['in_body_once']['passed'],
            'A card nobody can see is scoring for its words.',
        );
    }

    #[Test]
    public function an_item_in_the_other_language_does_not_leak(): void
    {
        $this->translate(['title' => 'من نحن'], 'ar');
        $this->translate(['title' => 'About'], 'en');

        $section = $this->page->sections()->create([
            'type' => 'cards', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = $section->items()->create(['sort_order' => 0, 'is_active' => true]);
        $item->translations()->create(['locale' => 'en', 'title' => 'Conference keepsakes']);

        $this->page->refresh();

        $this->assertFalse(
            $this->analyzer()->analyse($this->page, 'ar', 'conference keepsakes')['checks']['in_body_once']['passed'],
        );
        $this->assertTrue(
            $this->analyzer()->analyse($this->page, 'en', 'conference keepsakes')['checks']['in_body_once']['passed'],
        );
    }

    /** A page whose real headline is in its hero, not in the title field. */
    #[Test]
    public function a_hero_heading_counts_as_the_visible_heading(): void
    {
        $this->translate(['title' => 'من نحن']);

        $hero = $this->page->sections()->create([
            'type' => 'hero', 'sort_order' => 0, 'is_active' => true,
        ]);
        $hero->translations()->create(['locale' => 'ar', 'heading' => 'حرفة سعودية تليق بمقام جهتكم']);

        $this->page->refresh();

        $this->assertTrue(
            $this->analyzer()->analyse($this->page, 'ar', 'حرفة سعودية')['checks']['heading']['passed'],
            'The headline a reader actually meets first is not being read as the heading.',
        );
    }

    // ------------------------------------------------------------------ //
    // Over-repetition
    // ------------------------------------------------------------------ //

    /**
     * Warned about, never scored.
     *
     * A penalty here would drop the bar while the editor is doing the thing
     * the bar just asked for, and no wording survives that.
     */
    #[Test]
    public function a_phrase_repeated_past_the_limit_is_flagged_without_costing_points(): void
    {
        $term = 'هدايا مؤسسية';

        $natural = 'نصنع '.$term.' للجهات الحكومية والشركات في المملكة، '
            .'ونسلّمها بالتاريخ المتفق عليه مع تقرير أثر مفصّل يوثّق كل قطعة وحرفيّها '
            .'وكل مرحلة من مراحل الإنتاج والتسليم والتغليف والشحن حتى تصل إليكم.';

        $this->translate(['title' => 'من نحن', 'excerpt' => $natural]);
        $sane = $this->analyzer()->analyse($this->page, 'ar', $term);

        $this->assertFalse($sane['stuffed'], 'Ordinary copy was called stuffing.');

        // The same paragraph with the phrase jammed in twenty more times. Kept
        // long enough to clear STUFFING_MIN_WORDS: a short page has no share
        // worth measuring, which is the whole reason that gate exists.
        $this->translate([
            'title' => 'من نحن',
            'excerpt' => $natural.' '.str_repeat($term.' ', 20),
        ]);
        $stuffed = $this->analyzer()->analyse($this->page, 'ar', $term);

        $this->assertTrue($stuffed['stuffed'], 'Twenty repetitions in a row was not flagged.');
        $this->assertGreaterThan(KeywordAnalyzer::STUFFING_DENSITY, $stuffed['density']);
        $this->assertGreaterThanOrEqual($sane['score'], $stuffed['score'],
            'Stuffing cost points — the warning must not also be a penalty.');
    }

    // ------------------------------------------------------------------ //
    // The fingerprint
    // ------------------------------------------------------------------ //

    #[Test]
    public function the_fingerprint_changes_only_when_the_scored_content_does(): void
    {
        $this->translate(['title' => 'من نحن']);

        $before = $this->analyzer()->fingerprint($this->page, 'ar');

        // Not read by any check.
        $this->page->forceFill(['is_indexable' => false])->save();
        $this->assertSame($before, $this->analyzer()->fingerprint($this->page->refresh(), 'ar'));

        // Read by two.
        $this->translate(['title' => 'هدايا مؤسسية']);
        $this->assertNotSame($before, $this->analyzer()->fingerprint($this->page->refresh(), 'ar'));
    }

    /** A save that cannot change an answer must not rewrite sixty rows. */
    #[Test]
    public function an_unchanged_page_is_not_rescored(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => 'هدايا مؤسسية',
        ]);

        $keyword = PageKeyword::query()->sole();
        $stamp = $keyword->analyzed_at;

        $this->assertNotNull($keyword->content_hash, 'The fingerprint was not stored on entry.');

        $this->travel(2)->seconds();
        // A save that touches nothing the analyser reads.
        $this->page->forceFill(['sort_order' => 3])->save();

        $this->assertEquals($stamp, $keyword->refresh()->analyzed_at,
            'A save that changed nothing scored still rewrote the row.');
    }

    /** And the button that exists for doubt ignores the fingerprint. */
    #[Test]
    public function re_check_everything_runs_even_when_nothing_changed(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => 'هدايا مؤسسية',
        ]);

        $stamp = PageKeyword::query()->sole()->analyzed_at;

        $this->travel(2)->seconds();

        $this->actingAs($this->admin)->post('/admin/seo/keywords/reanalyse', [
            'page_id' => $this->page->id, 'locale' => 'ar',
        ])->assertRedirect();

        $this->assertNotEquals($stamp, PageKeyword::query()->sole()->analyzed_at,
            'The re-check button did nothing, which is indistinguishable from it being broken.');
    }

    /**
     * The screen says a number is out of date rather than showing it as fact.
     *
     * The automatic pass is queued. If a worker is not running, this flag is
     * the only thing between an editor and a stale colour they cannot tell
     * from a current one.
     */
    #[Test]
    public function a_score_from_before_the_last_edit_is_marked_as_updating(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => 'هدايا مؤسسية',
        ]);

        $fresh = $this->actingAs($this->admin)
            ->get('/admin/seo/keywords?page_id='.$this->page->id.'&locale=ar')
            ->viewData('page')['props']['keywords'];

        $this->assertFalse($fresh[0]['stale']);

        // The page moves on without the job running.
        PageKeyword::query()->sole()->forceFill(['content_hash' => 'stale-by-hand'])->save();

        $stale = $this->actingAs($this->admin)
            ->get('/admin/seo/keywords?page_id='.$this->page->id.'&locale=ar')
            ->viewData('page')['props']['keywords'];

        $this->assertTrue($stale[0]['stale'],
            'A score describing an older version of the page was presented as current.');
    }

    // ------------------------------------------------------------------ //
    // Entry, duplicates, deletion
    // ------------------------------------------------------------------ //

    #[Test]
    public function a_paste_creates_one_row_per_phrase(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id,
            'locale' => 'ar',
            'terms' => "هدايا مؤسسية\nدروع تكريم، تذكارات المؤتمرات",
        ])->assertRedirect();

        $this->assertSame(3, PageKeyword::query()->count());
        $this->assertNotNull(PageKeyword::query()->first()->analyzed_at);
    }

    #[Test]
    public function a_repeated_phrase_is_ignored_rather_than_rejected(): void
    {
        $this->translate(['title' => 'من نحن']);

        $post = fn (string $terms) => $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => $terms,
        ]);

        $post('هدايا مؤسسية');
        // The same phrase twice inside one paste, and once more from before.
        $post("هدايا مؤسسية\nهدايا مؤسسية\nدروع تكريم")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, PageKeyword::query()->count(),
            'A duplicate created a second row, or a valid phrase beside it was refused.');
    }

    /** Two spellings of one phrase are one keyword, not two. */
    #[Test]
    public function two_spellings_of_the_same_phrase_collapse(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id,
            'locale' => 'ar',
            'terms' => "الحرفة السعودية\nالحرفه السعوديه\nالحِرفة السعوديّة",
        ]);

        $this->assertSame(1, PageKeyword::query()->count());
    }

    #[Test]
    public function deleting_a_page_deletes_its_keywords(): void
    {
        PageKeyword::query()->create([
            'page_id' => $this->page->id, 'locale' => 'ar',
            'keyword' => 'اختبار', 'keyword_normalized' => 'اختبار', 'score' => 0,
        ]);

        $this->page->forceDelete();

        $this->assertSame(0, PageKeyword::query()->count());
    }

    // ------------------------------------------------------------------ //
    // The automatic part
    // ------------------------------------------------------------------ //

    #[Test]
    public function saving_the_page_rescores_its_keywords(): void
    {
        $term = 'تحكيم جودة النخيل';

        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => $term,
        ]);

        $keyword = PageKeyword::query()->sole();
        $before = $keyword->score;

        // The editor does what the panel told them to do.
        $this->translate(['title' => $term, 'meta_title' => $term]);

        $keyword->refresh();

        $this->assertGreaterThan($before, $keyword->score,
            'The page was edited and the score did not follow — nothing else on this screen can be trusted after that.');
        $this->assertTrue($keyword->checks['meta_title']['passed']);
    }

    #[Test]
    public function saving_a_section_rescores_too(): void
    {
        $term = 'سعف النخيل';

        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => $term,
        ]);

        $keyword = PageKeyword::query()->sole();
        $before = $keyword->score;

        $section = $this->page->sections()->create([
            'type' => 'rich_text', 'sort_order' => 0, 'is_active' => true,
        ]);
        $section->translations()->create(['locale' => 'ar', 'body' => "<p>{$term} من الأحساء.</p>"]);

        $this->assertGreaterThan($before, $keyword->refresh()->score);
    }

    #[Test]
    public function saving_a_card_rescores_too(): void
    {
        $term = 'تذكارات المؤتمرات';

        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => $term,
        ]);

        $keyword = PageKeyword::query()->sole();
        $before = $keyword->score;

        $section = $this->page->sections()->create([
            'type' => 'cards', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = $section->items()->create(['sort_order' => 0, 'is_active' => true]);
        $item->translations()->create(['locale' => 'ar', 'title' => $term]);

        $this->assertGreaterThan($before, $keyword->refresh()->score,
            'A card was added and the score did not follow it.');
    }

    /**
     * Alt text is scored, and attaching an image is what makes it reachable.
     *
     * The alt check is the one nobody thinks to re-run by hand, so the
     * attachment itself has to trigger the pass.
     */
    #[Test]
    public function attaching_an_image_brings_its_alt_text_into_the_score(): void
    {
        $term = 'سعف النخيل';

        $this->translate(['title' => 'من نحن']);

        $section = $this->page->sections()->create([
            'type' => 'gallery', 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => $term,
        ]);

        $keyword = PageKeyword::query()->sole();
        $this->assertFalse($keyword->checks['image_alt']['passed']);

        $media = Media::query()->create([
            'model_type' => Page::class,
            'model_id' => $this->page->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'library',
            'name' => 'palm',
            'file_name' => 'palm.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $media->translations()->create(['locale' => 'ar', 'alt_text' => "سلة من {$term}"]);

        $section->syncAttachedMedia('gallery', [$media->id]);

        $this->assertTrue($keyword->refresh()->checks['image_alt']['passed'],
            'An image was attached and its alt text never reached the score.');
    }

    /** Editing one language must not restamp the other. */
    #[Test]
    public function an_english_edit_leaves_the_arabic_scores_alone(): void
    {
        $this->translate(['title' => 'من نحن'], 'ar');
        $this->translate(['title' => 'About us'], 'en');

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar', 'terms' => 'هدايا مؤسسية',
        ]);

        $arabic = PageKeyword::query()->sole();
        $stamp = $arabic->analyzed_at;

        $this->travel(2)->seconds();
        $this->translate(['title' => 'Corporate gifts'], 'en');

        $this->assertEquals($stamp, $arabic->refresh()->analyzed_at,
            'An English edit rewrote the Arabic rows.');
    }

    // ------------------------------------------------------------------ //
    // The page's own subject
    // ------------------------------------------------------------------ //

    #[Test]
    public function naming_a_main_keyword_unseats_the_previous_one(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar',
            'terms' => "هدايا مؤسسية\nدروع تكريم",
        ]);

        [$first, $second] = PageKeyword::query()->orderBy('id')->get()->all();

        $this->actingAs($this->admin)
            ->put("/admin/seo/keywords/{$first->id}/primary")
            ->assertRedirect();

        $this->assertTrue($first->refresh()->is_primary);

        $this->actingAs($this->admin)->put("/admin/seo/keywords/{$second->id}/primary");

        $this->assertTrue($second->refresh()->is_primary);
        $this->assertFalse($first->refresh()->is_primary,
            'Two keywords claimed to be the page subject at once.');
    }

    /** Each language names its own, because each is a different page to a reader. */
    #[Test]
    public function the_two_languages_keep_separate_main_keywords(): void
    {
        $this->translate(['title' => 'من نحن'], 'ar');
        $this->translate(['title' => 'About'], 'en');

        $post = fn (string $locale, string $term) => $this->actingAs($this->admin)
            ->post('/admin/seo/keywords', [
                'page_id' => $this->page->id, 'locale' => $locale, 'terms' => $term,
            ]);

        $post('ar', 'هدايا مؤسسية');
        $post('en', 'corporate gifts');

        $arabic = PageKeyword::query()->forLocale('ar')->sole();
        $english = PageKeyword::query()->forLocale('en')->sole();

        $this->actingAs($this->admin)->put("/admin/seo/keywords/{$arabic->id}/primary");
        $this->actingAs($this->admin)->put("/admin/seo/keywords/{$english->id}/primary");

        $this->assertTrue($arabic->refresh()->is_primary);
        $this->assertTrue($english->refresh()->is_primary,
            'Naming the English subject cleared the Arabic one.');
    }

    /** Strongest first, with the page's subject pinned above all of it. */
    #[Test]
    public function the_list_reads_strongest_first_under_the_main_keyword(): void
    {
        $term = 'هدايا مؤسسية';

        $this->translate(['title' => $term, 'meta_title' => $term, 'excerpt' => "{$term} {$term}"]);

        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'page_id' => $this->page->id, 'locale' => 'ar',
            'terms' => "{$term}\nدروع تكريم\nتذكارات المؤتمرات",
        ]);

        // The weakest of the three is made the subject, so the pinning is
        // visible rather than coinciding with the score order.
        $weakest = PageKeyword::query()->orderBy('score')->first();
        $this->actingAs($this->admin)->put("/admin/seo/keywords/{$weakest->id}/primary");

        $shown = $this->actingAs($this->admin)
            ->get('/admin/seo/keywords?page_id='.$this->page->id.'&locale=ar')
            ->viewData('page')['props']['keywords'];

        $this->assertTrue($shown[0]['isPrimary'], 'The page subject is not at the top of its own list.');
        $this->assertSame($weakest->keyword, $shown[0]['keyword']);

        $rest = array_slice($shown, 1);
        $scores = array_column($rest, 'score');

        $this->assertSame($scores, array_reverse(collect($scores)->sort()->values()->all()),
            'The rest of the list is not ordered strongest to weakest.');
    }

    // ------------------------------------------------------------------ //
    // The screen
    // ------------------------------------------------------------------ //

    #[Test]
    public function the_screen_renders_and_offers_every_published_page(): void
    {
        $this->translate(['title' => 'من نحن']);

        $props = $this->actingAs($this->admin)
            ->get('/admin/seo/keywords')->assertOk()
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['pages']);
        $this->assertSame(100, array_sum($props['weights']));
        $this->assertSame(PageKeyword::WEAK_BELOW, $props['bands']['weakBelow']);
    }

    #[Test]
    public function a_visitor_cannot_reach_the_screen(): void
    {
        $this->get('/admin/seo/keywords')->assertRedirect('/admin/login');
    }
}
