<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpactMetric;
use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Page;
use App\Models\Story;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Crm\Drivers\LeadPayload;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /training is the only page on this site with two readers.
 *
 * An artisan asking to join a track and an institution asking to fund one read
 * the same page and want opposite things. It used to show tracks to the first
 * and then end at a form addressed to the second, so the artisan reached the
 * bottom and found no door. These guard the way that was fixed — and guard it
 * against the obvious wrong fix, which is a second form.
 */
class TrainingPageServesTwoAudiencesTest extends TestCase
{
    use RefreshDatabase;

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

    // ---------------------------------------------------------------- //
    // The two doors
    // ---------------------------------------------------------------- //

    /** Each audience is named, and each is given something to press. */
    #[Test]
    public function the_page_offers_a_way_in_to_both_audiences(): void
    {
        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringContainsString('للحرفيين والحرفيات', $text);
        $this->assertStringContainsString('للجهات الراعية', $text);
        $this->assertStringContainsString('التحقوا بمسار', $text);
        $this->assertStringContainsString('ارعوا مسارًا', $text);
    }

    /**
     * Two doors, one room.
     *
     * §6.1 permits exactly one form per page. The temptation on a page with
     * two audiences is to give each of them their own, which doubles the
     * fields to maintain and halves the evidence about which one converts.
     */
    #[Test]
    public function the_two_doors_lead_to_a_single_form(): void
    {
        $body = $this->get('/ar/training')->assertOk()->getContent();

        $this->assertSame(1, substr_count($body, 'name="contact"'),
            'The training page carries more than one contact field.');

        /*
         * Counted as a class TOKEN, not as the verbatim attribute: Vue merges
         * a bound modifier into `class="lead-input lead-input--mono"`, so
         * matching the attribute string finds two of the three fields. That
         * lesson cost three attempts on the artisan page.
         *
         * And counted against the ENABLED fields rather than a literal, for
         * the reason `submission()` below already gives: the number of fields
         * is the client's to change from the panel. Hard-coding it made this
         * test fail the day the message field was turned on — which is the
         * form working, not breaking. What matters here is that both doors
         * land on ONE form, and that is what `name="contact"` above proves.
         */
        $this->assertSame(LeadField::query()->enabled()->count(),
            preg_match_all('/class="[^"]*\blead-input\b/', $body),
            'The page renders a different number of fields than the panel enables.');
    }

    // ---------------------------------------------------------------- //
    // The interest tag
    // ---------------------------------------------------------------- //

    /**
     * A submission carrying whatever the shipped form actually asks for.
     *
     * Built from the enabled fields rather than from a fixed list, so the day
     * the client turns a fourth one on in the panel these tests keep testing
     * the interest tag instead of failing about a field they had hard-coded.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        $answers = LeadField::query()->enabled()->get()
            ->mapWithKeys(fn (LeadField $field): array => [
                $field->key => match (true) {
                    $field->key === LeadField::KEY_CONTACT => 'weaver@example.sa',
                    $field->type === 'tel' => '0512345678',
                    default => 'أمد',
                },
            ])
            ->all();

        return array_merge($answers, $overrides);
    }

    /** Which button was pressed reaches the record, unasked. */
    #[Test]
    public function the_interest_tag_is_stored_with_the_lead(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', $this->submission(['interest' => 'trainee']));

        $this->assertSame('trainee', Lead::sole()->interest);
    }

    /**
     * A lead from a visitor who pressed nothing carries no tag.
     *
     * Defaulting it to either audience would be a guess wearing the clothes of
     * a fact, and the sales team would learn to distrust the column.
     */
    #[Test]
    public function a_visitor_who_said_nothing_is_not_labelled(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', $this->submission());

        $this->assertNull(Lead::sole()->interest);
    }

    /**
     * A mistyped tag must never cost a lead.
     *
     * The value comes from a section setting the client edits, so it is
     * validated by length and nothing else — a whitelist here would turn a
     * typo in the panel into a rejected submission, and the number of leads is
     * the only thing this site is measured on (§1).
     */
    #[Test]
    public function an_unknown_tag_is_kept_rather_than_refused(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', $this->submission(['interest' => 'sponsorr']))
            ->assertSessionHasNoErrors();

        $this->assertSame('sponsorr', Lead::sole()->interest);
    }

    #[Test]
    public function the_tag_reaches_the_crm_payload(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', $this->submission(['interest' => 'sponsor']));

        // The trait itself, not a driver: this is the shape every provider is
        // sent, which is the whole point of the abstraction (§6.3).
        $payload = (new class
        {
            use LeadPayload;

            public function expose(Lead $lead): array
            {
                return $this->payload($lead);
            }
        })->expose(Lead::sole());

        $this->assertSame('sponsor', $payload['source']['interest']);
    }

    #[Test]
    public function the_tag_reaches_the_export(): void
    {
        Queue::fake();
        Notification::fake();

        $this->post('/leads', $this->submission(['interest' => 'sponsor']));

        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $csv = $this->actingAs($admin)->get('/admin/leads/export')
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('الاهتمام', $csv);
        $this->assertStringContainsString('sponsor', $csv);
    }

    // ---------------------------------------------------------------- //
    // The tracks
    // ---------------------------------------------------------------- //

    /**
     * The outcome line is the card's spine.
     *
     * It is what separates a track from an awareness workshop, and it is the
     * line an institution weighing a sponsorship actually reads — so it is a
     * required field, not an optional flourish.
     */
    #[Test]
    public function every_track_states_what_the_trainee_walks_out_with(): void
    {
        foreach (TrainingProgram::query()->visible()->with('translations')->get() as $program) {
            $this->assertNotNull($program->t('outcomes'),
                "Track {$program->slug} describes no outcome.");
        }

        $this->assertStringContainsString(
            'ملف تسعير',
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
        );
    }

    /** The editor refuses a track described without its outcome. */
    #[Test]
    public function the_panel_will_not_save_a_track_with_no_outcome(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $program = TrainingProgram::query()->firstOrFail();

        $this->actingAs($admin)
            ->from("/admin/content/training-programs/{$program->id}")
            ->patch("/admin/content/training-programs/{$program->id}", [
                'active' => true,
                'attributes' => ['slug' => $program->slug, 'duration_weeks' => 8],
                'translations' => [
                    'ar' => ['name' => 'مسار', 'summary' => 'وصف', 'outcomes' => '', 'next_cohort' => '', 'body' => ''],
                    'en' => ['name' => '', 'summary' => '', 'outcomes' => '', 'next_cohort' => '', 'body' => ''],
                ],
            ])
            ->assertSessionHasErrors('translations.ar.outcomes');
    }

    /**
     * A language left blank throughout is still "not translated" — the outcome
     * is required for a locale being written, never for one left alone (§12).
     */
    #[Test]
    public function an_untouched_language_is_not_made_compulsory_by_it(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $program = TrainingProgram::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch("/admin/content/training-programs/{$program->id}", [
                'active' => true,
                'attributes' => ['slug' => $program->slug, 'duration_weeks' => 8],
                'translations' => [
                    'ar' => ['name' => 'مسار', 'summary' => 'وصف', 'outcomes' => 'مخرَج', 'next_cohort' => '', 'body' => ''],
                    'en' => ['name' => '', 'summary' => '', 'outcomes' => '', 'next_cohort' => '', 'body' => ''],
                ],
            ])
            ->assertSessionHasNoErrors();
    }

    /** Switching a track off takes it off the page without deleting it. */
    #[Test]
    public function a_track_switched_off_leaves_the_page_but_not_the_record(): void
    {
        $program = TrainingProgram::query()->firstOrFail();
        $name = (string) $program->t('name');

        $program->forceFill(['is_active' => false])->save();

        $this->assertStringNotContainsString(
            $name,
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
        );

        $this->assertDatabaseHas('training_programs', ['id' => $program->id]);
    }

    /** The duration badge is read from the field, never typed into the copy. */
    #[Test]
    public function the_duration_badge_follows_the_field(): void
    {
        TrainingProgram::query()->firstOrFail()->forceFill(['duration_weeks' => 11])->save();

        $this->assertStringContainsString(
            '11',
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
        );
    }

    /**
     * No cohort is announced that nobody has scheduled.
     *
     * One render per test, here and below — the SSR gateway serves the first
     * render of a test and returns an empty document for a second, so a
     * before/after pair inside one method silently asserts against nothing.
     * The lesson `ImpactPageProvesItsClaimsTest` records; these are its pairs.
     */
    #[Test]
    public function no_next_cohort_line_appears_while_the_field_is_empty(): void
    {
        $this->assertNull(TrainingProgram::query()->firstOrFail()->t('next_cohort'));

        $this->assertStringNotContainsString(
            'الدفعة القادمة',
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
        );
    }

    #[Test]
    public function filling_the_next_cohort_puts_the_line_on_the_card(): void
    {
        TrainingProgram::query()->firstOrFail()->translations()
            ->where('locale', 'ar')->update(['next_cohort' => 'مارس ٢٠٢٧']);

        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringContainsString('الدفعة القادمة', $text);
        $this->assertStringContainsString('مارس ٢٠٢٧', $text);
    }

    // ---------------------------------------------------------------- //
    // The figures
    // ---------------------------------------------------------------- //

    /** One record, read twice — the same rule /impact is held to. */
    #[Test]
    public function the_training_figures_come_from_the_one_register(): void
    {
        ImpactMetric::query()->where('key', 'training-hours')->update(['value_numeric' => 5731]);

        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringContainsString('5,731', $text,
            'The training page does not read its figure from the register.');
        $this->assertStringNotContainsString('4,200', $text,
            'The training page still carries the previous figure.');
    }

    /**
     * A training page does not borrow the delivery numbers.
     *
     * «ساعة تدريب» beside «قطعة حرفية سُلّمت» would be a training claim made
     * out of unrelated data, which is the failure the section's `keys` setting
     * exists to prevent.
     */
    #[Test]
    public function it_does_not_show_the_site_wide_figures(): void
    {
        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringNotContainsString('قطعة حرفية سُلّمت', $text);
        $this->assertStringNotContainsString('18,500', $text);
    }

    /** A figure nobody has counted is not published as a blank. */
    #[Test]
    public function an_unmeasured_figure_stays_off_the_page(): void
    {
        $this->assertNull(
            ImpactMetric::query()->where('key', 'training-graduates')->sole()->value_numeric,
        );

        $this->assertStringNotContainsString(
            'متدربًا أنهى مسارًا',
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
            'A figure with no value is being published as a caption over a blank.',
        );
    }

    /** Typing the number in the panel is the only step needed to publish it. */
    #[Test]
    public function typing_the_number_publishes_the_figure(): void
    {
        ImpactMetric::query()->where('key', 'training-graduates')
            ->update(['value_numeric' => 64]);

        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringContainsString('متدربًا أنهى مسارًا', $text);
        $this->assertStringContainsString('64', $text);
    }

    // ---------------------------------------------------------------- //
    // Graduates
    // ---------------------------------------------------------------- //

    /**
     * The graduates section is silent until a real story is tagged.
     *
     * What happened to somebody who took a track is theirs to say. A drafted
     * graduate testimonial is not placeholder copy, it is a fabricated
     * endorsement — and this is the page where one would be most persuasive
     * and most dishonest (§22.1).
     */
    #[Test]
    public function the_graduates_section_is_silent_while_no_story_is_tagged(): void
    {
        $this->assertSame(0, Story::query()->whereNotNull('tag')->count(),
            'A seeder is drafting a graduate testimonial again.');

        $this->assertStringNotContainsString(
            'من خريجي المسارات',
            $this->rendered($this->get('/ar/training')->assertOk()->getContent()),
        );
    }

    /** Tagging one real story in the panel switches the section on. */
    #[Test]
    public function tagging_a_story_switches_the_graduates_section_on(): void
    {
        $story = Story::query()->visible()->firstOrFail();
        $story->forceFill(['tag' => 'training-graduate'])->save();

        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        $this->assertStringContainsString('من خريجي المسارات', $text);
        $this->assertStringContainsString((string) $story->t('quote'), $text);
    }

    /** Tagging one story does not remove it from the page it came from. */
    #[Test]
    public function a_tagged_story_still_belongs_to_the_impact_page(): void
    {
        $story = Story::query()->visible()->firstOrFail();
        $story->forceFill(['tag' => 'training-graduate'])->save();

        $this->assertStringContainsString(
            (string) $story->t('quote'),
            $this->rendered($this->get('/ar/impact')->assertOk()->getContent()),
        );
    }

    // ---------------------------------------------------------------- //
    // What this page must never become
    // ---------------------------------------------------------------- //

    /**
     * No price, no fee, no promised income.
     *
     * §2.2 forbids this site growing a commercial function, and a page about
     * training is exactly where one starts: a course fee is a price list, and
     * a stated income is a guarantee nobody can honour.
     */
    #[Test]
    public function the_page_quotes_no_price_and_promises_no_income(): void
    {
        $text = $this->rendered($this->get('/ar/training')->assertOk()->getContent());

        foreach (['ريال', 'ر.س', 'رسوم', 'دخل مضمون', 'راتب', 'وظيفة مضمونة'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text,
                "The training page has grown a commercial claim: «{$forbidden}».");
        }
    }

    /**
     * A track is illustrated by its own room or by nothing.
     *
     * The frames used to carry catalogue photography, so the craft-business
     * track arrived illustrated with a wall clock. This section is about
     * people learning; a finished product is not what a track is.
     */
    #[Test]
    public function no_track_is_illustrated_with_a_catalogue_photograph(): void
    {
        foreach (TrainingProgram::query()->with('media')->get() as $program) {
            $file = $program->getFirstMedia('hero')?->file_name;

            $this->assertFalse(
                $file !== null && preg_match('/^(craft-|\d+\.)/', $file) === 1,
                "Track {$program->slug} is illustrated with a catalogue photograph.",
            );
        }
    }

    /** §12: an English reader is never served the Arabic questions. */
    #[Test]
    public function the_english_page_carries_no_arabic_copy(): void
    {
        $text = $this->rendered($this->get('/en/training')->assertOk()->getContent());

        $this->assertStringContainsString('Who these tracks are for', $text);
        $this->assertStringNotContainsString('هل التدريب مجاني للحرفي؟', $text);
        $this->assertStringNotContainsString('للجهات الراعية', $text);
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
