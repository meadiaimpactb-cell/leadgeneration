<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageKeyword;
use App\Models\User;
use App\Services\Seo\KeywordAnalyzer;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

        $this->seed([RolesSeeder::class, StructureSeeder::class, NavigationSeeder::class]);

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
        $analyzer = $this->analyzer();

        $this->assertSame(
            $analyzer->normalise('إهداء أمد'),
            $analyzer->normalise('اهداء امد'),
        );
    }

    #[Test]
    public function diacritics_are_ignored(): void
    {
        $analyzer = $this->analyzer();

        $this->assertSame(
            $analyzer->normalise('الحِرفة السعوديّة'),
            $analyzer->normalise('الحرفة السعودية'),
        );
    }

    #[Test]
    public function taa_marbuta_and_alef_maqsura_are_unified(): void
    {
        $analyzer = $this->analyzer();

        $this->assertSame($analyzer->normalise('هدية'), $analyzer->normalise('هديه'));
        $this->assertSame($analyzer->normalise('مقهى'), $analyzer->normalise('مقهي'));
    }

    #[Test]
    public function tatweel_and_arabic_digits_are_folded(): void
    {
        $analyzer = $this->analyzer();

        $this->assertSame($analyzer->normalise('حــرف'), $analyzer->normalise('حرف'));
        $this->assertSame($analyzer->normalise('رؤية ٢٠٣٠'), $analyzer->normalise('رؤية 2030'));
    }

    #[Test]
    public function english_is_lowercased_and_trimmed(): void
    {
        $this->assertSame('corporate gifts', $this->analyzer()->normalise('  Corporate  GIFTS ', 'en'));
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

    // ------------------------------------------------------------------ //
    // Entry, duplicates, deletion
    // ------------------------------------------------------------------ //

    #[Test]
    public function a_paste_creates_one_row_per_phrase(): void
    {
        $this->translate(['title' => 'من نحن']);

        $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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

        $post = fn (string $terms) => $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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

        $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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

        $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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

        $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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

    /** Editing one language must not restamp the other. */
    #[Test]
    public function an_english_edit_leaves_the_arabic_scores_alone(): void
    {
        $this->translate(['title' => 'من نحن'], 'ar');
        $this->translate(['title' => 'About us'], 'en');

        $this->actingAs($this->admin)->post('/admin/seo/page-keywords', [
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
    // The screen
    // ------------------------------------------------------------------ //

    #[Test]
    public function the_screen_renders_and_offers_every_published_page(): void
    {
        $this->translate(['title' => 'من نحن']);

        $props = $this->actingAs($this->admin)
            ->get('/admin/seo/page-keywords')->assertOk()
            ->viewData('page')['props'];

        $this->assertNotEmpty($props['pages']);
        $this->assertSame(100, array_sum($props['weights']));
        $this->assertSame(PageKeyword::WEAK_BELOW, $props['bands']['weakBelow']);
    }

    #[Test]
    public function the_site_wide_keyword_screen_still_works(): void
    {
        $this->actingAs($this->admin)->get('/admin/seo/keywords')->assertOk();
    }

    #[Test]
    public function a_visitor_cannot_reach_the_screen(): void
    {
        $this->get('/admin/seo/page-keywords')->assertRedirect('/admin/login');
    }
}
