<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The solutions panel is a chooser, not a list of links.
 *
 * A visitor hesitating between «الشركاء» and «شركات القطاع الخاص» is one wrong
 * click from a page written for somebody else. The numbered rows and the line
 * under each name exist to settle that before the click.
 */
class SolutionsPanelTest extends TestCase
{
    use RefreshDatabase;

    /** The approved order. The numbering is meaningless if it drifts. */
    private const ORDER = [
        'الجهات الحكومية وشبه الحكومية',
        'شركات القطاع الخاص',
        'الشركاء',
        'الحرفيون',
    ];

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

    #[Test]
    public function the_panel_lists_the_four_segments_in_the_approved_order(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        preg_match_all('/class="panel__label"[^>]*>\s*([^<]+?)\s*</u', $body, $matches);

        $this->assertSame(self::ORDER, $matches[1],
            'The solutions panel lost a segment or reordered them.');
    }

    /**
     * The counter runs 01–04, in Latin digits.
     *
     * It is the same running index the pages use, which is the whole reason it
     * is here: the menu should read as part of the same system, not as a
     * decorated list. Latin because `NumeralFormattingTest` forbids
     * Arabic-Indic digits site-wide.
     */
    #[Test]
    public function every_row_carries_its_number(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        preg_match_all('/class="panel__num"[^>]*>\s*([0-9]{2})\s*</u', $body, $matches);

        $this->assertSame(['01', '02', '03', '04'], $matches[1],
            'The panel numbering is missing, out of order, or not two digits.');
    }

    /**
     * The descriptions come from the panel, not the template.
     *
     * They are copy, so they live in `navigation_item_translations.description`
     * where the client can edit them (§22.1). Asserted through the database
     * rather than by matching the strings, so rewording them in the panel does
     * not fail this test.
     */
    #[Test]
    public function each_row_carries_a_description_read_from_the_database(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        preg_match_all('/class="panel__desc"[^>]*>\s*([^<]+?)\s*</u', $body, $matches);

        $this->assertCount(4, $matches[1], 'A segment is missing its description line.');

        foreach ($matches[1] as $description) {
            $this->assertDatabaseHas('navigation_item_translations', [
                'locale' => 'ar',
                'description' => $description,
            ]);
        }
    }

    /** The English panel carries English descriptions, or none (§12). */
    #[Test]
    public function the_english_panel_is_written_in_english(): void
    {
        $body = $this->get('/en')->assertOk()->getContent();

        preg_match_all('/class="panel__desc"[^>]*>\s*([^<]+?)\s*</u', $body, $matches);

        $this->assertCount(4, $matches[1]);

        foreach ($matches[1] as $description) {
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $description,
                'The English panel is serving Arabic descriptions.');
        }
    }

    /** The disclosure states whether it is open, for anyone not using a mouse. */
    #[Test]
    public function the_panel_declares_whether_it_is_open(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/aria-expanded="(true|false)"[^>]*aria-controls="submenu-\d+"/', $body,
            'The solutions disclosure does not describe its own state.');
    }
}
