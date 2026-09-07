<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The caption beside each home-page section number (§0.3).
 *
 * "01 / 05 · MANIFESTO". The number was already derived from the section's
 * place in the `sections` rows; the caption was five English words typed into
 * Home.vue, which meant reordering the blocks in the panel renumbered them
 * while their captions stayed put.
 *
 * The approved design is unchanged — these are the same words. What these
 * tests hold is that they now FOLLOW the section: a caption comes from the
 * section's own settings when the client writes one, and otherwise from the
 * shipped label for its type.
 */
class HomeSectionCaptionsTest extends TestCase
{
    use RefreshDatabase;

    /** The captions the approved design shows, keyed by section type. */
    private const APPROVED = [
        'intro_statement' => 'manifesto',
        'solutions_grid' => 'solutions',
        'gallery' => 'showroom',
        'sector_spotlight' => 'sectors',
        'story_carousel' => 'voices',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * SUSPENDED, NOT DELETED — pending the decision in docs/landing-page.md §4.
         *
         * These check `caption()` in Home.vue by fetching `/`. Since the
         * management decision of 7 September 2026 the root serves the single
         * landing page, which numbers nothing and captions nothing, so there
         * is no longer a URL that reaches the code under test.
         *
         * Home.vue and HomeController are still in the tree on purpose: if any
         * of the eleven retired pages come back, reverting is one line in
         * routes/web.php, and these tests come back with them. Deleting the
         * component and its tests is the same decision as running
         * LandingRedirectsSeeder, and that decision is Amad Craft's.
         *
         * Skipped loudly rather than removed, so the cost of leaving the
         * question open stays visible in every run.
         */
        $this->markTestSkipped(
            'Home.vue is unrouted since the landing-page switchover; see docs/landing-page.md §4.'
        );

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /**
     * Vue emits fragment markers between a tag and its text, which is how the
     * first version of this check quietly measured nothing. They are stripped
     * before matching, and the Inertia payload is removed so a caption found
     * here is one that was really drawn.
     */
    private function rendered(string $locale): string
    {
        $html = $this->get("/{$locale}")->assertOk()->getContent();
        $html = preg_replace('/<script[^>]*type="application\/json"[^>]*>.*?<\/script>/s', ' ', $html) ?? '';

        return str_replace(['<!--[-->', '<!--]-->'], '', $html);
    }

    #[Test]
    public function the_approved_caption_is_shown_for_every_numbered_section(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $html = $this->rendered($locale);

            foreach (self::APPROVED as $type => $caption) {
                $this->assertStringContainsString(
                    $caption,
                    $html,
                    "/{$locale} lost the caption for {$type}",
                );
            }
        }
    }

    /** Nothing is written in the template — the words come from resources/lang. */
    #[Test]
    public function every_caption_comes_from_a_translation_key(): void
    {
        foreach (self::APPROVED as $type => $caption) {
            $this->assertSame($caption, __("common.section_label_{$type}"));
        }
    }

    /**
     * The point of the change: a caption the client sets on the section
     * itself wins, so it travels with the block rather than with this file.
     */
    #[Test]
    public function a_caption_written_on_the_section_overrides_the_shipped_one(): void
    {
        $section = Section::query()->where('type', 'story_carousel')->firstOrFail();

        $section->forceFill([
            'settings' => array_merge($section->settings ?? [], ['index_label' => 'chosen-in-panel']),
        ])->save();

        $html = $this->rendered('ar');

        $this->assertStringContainsString('chosen-in-panel', $html);
        $this->assertStringNotContainsString('>voices<', $html);
    }

    /**
     * Whitespace is not a caption. Without this, clearing the field in the
     * panel would print an empty label instead of falling back.
     */
    #[Test]
    public function a_blank_setting_falls_back_rather_than_printing_nothing(): void
    {
        $section = Section::query()->where('type', 'story_carousel')->firstOrFail();

        $section->forceFill([
            'settings' => array_merge($section->settings ?? [], ['index_label' => '   ']),
        ])->save();

        $this->assertStringContainsString('voices', $this->rendered('ar'));
    }
}
