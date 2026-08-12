<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Partner;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The partners published on amadcraft.sa.
 *
 * Regression guard for two defects that shipped together:
 *
 *   1. The partner rows were invented placeholders ("Events partner", "Craft
 *      accreditation body"). Two of the three groups asserted relationships —
 *      accreditation, client — that Amad Craft has never published.
 *   2. The logo collection was filled round-robin from the craft photo library,
 *      so each organisation's row carried a photograph of a woven basket where
 *      its mark should be.
 *
 * The second one is why the logo assertions here check the *file*, not just
 * that some media exists: "has a logo" passed the whole time it was wrong.
 */
class PartnerLogosTest extends TestCase
{
    use RefreshDatabase;

    /** Name => logo file, in the order amadcraft.sa lists them. */
    private const EXPECTED = [
        'Alinma Bank' => 'alinma.webp',
        'Al Ahsa Chamber' => 'al-ahsa-chamber.webp',
        'Heritage Commission' => 'heritage-commission.webp',
        'Impact Valley Capital' => 'impact-valley.webp',
        'AMAD — Sustainability & Social Responsibility Program' => 'amad-program.webp',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            StructureSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
            DemoExtrasSeeder::class,
        ]);
    }

    /**
     * Site-wide rows, which is what this page publishes.
     *
     * Scoped to `sector_id IS NULL` since DemoExtrasSeeder began pinning
     * stand-in client marks to a segment: those belong to that segment's own
     * strip and never to this list. The guard is unchanged in substance —
     * this page still shows exactly the five and nothing else.
     */
    private function published(): Builder
    {
        return Partner::query()->visible()->whereNull('sector_id');
    }

    #[Test]
    public function the_seeded_partners_are_the_ones_the_official_site_publishes(): void
    {
        $this->assertSame(
            array_keys(self::EXPECTED),
            $this->published()->pluck('name')->all(),
        );
    }

    #[Test]
    public function every_partner_carries_its_own_logo_and_not_a_craft_photograph(): void
    {
        foreach ($this->published()->get() as $partner) {
            $logo = $partner->getFirstMedia('logo');

            $this->assertNotNull($logo, "{$partner->name} has no logo");
            $this->assertSame(
                self::EXPECTED[$partner->name],
                $logo->file_name,
                "{$partner->name} carries the wrong image",
            );
        }
    }

    #[Test]
    public function a_logo_is_described_by_the_organisations_own_name(): void
    {
        // The alt text used to read "a craft piece made by Amad Craft" on every
        // logo, which tells a screen reader nothing about which mark it is.
        $logo = Partner::query()->where('name', 'Alinma Bank')->sole()->getFirstMedia('logo');

        $this->assertSame('شعار مصرف الإنماء', $logo->translation('ar')?->alt_text);
        $this->assertSame('Alinma Bank logo', $logo->translation('en')?->alt_text);
    }

    #[Test]
    public function no_organisation_is_filed_as_an_accreditor_or_a_client(): void
    {
        // amadcraft.sa lists one group. Filing a real body under a group it
        // never claimed is an assertion about that body, not a layout choice.
        $this->assertSame(
            [Partner::TYPE_PARTNER],
            // reorder(): visible() sorts by sort_order, and MySQL rejects
            // an ORDER BY on a column a DISTINCT does not select.
            $this->published()->reorder()->distinct()->pluck('type')->all(),
        );
    }

    /**
     * And a segment's stand-in marks stay on that segment's page.
     *
     * The reason the scope above is safe: the rows exist, they are just not
     * this page's. If they ever reach it they arrive under «عملاؤنا» — which
     * would present five blank marks as Amad Craft's clients, the exact claim
     * the test above exists to prevent.
     */
    #[Test]
    public function a_logo_pinned_to_a_segment_never_reaches_the_partners_page(): void
    {
        $pinned = Partner::query()->whereNotNull('sector_id')->get();

        $this->assertNotEmpty($pinned, 'Nothing is pinned to a segment: this guard is testing nothing.');

        $props = $this->get('/ar/partners')->assertOk()->viewData('page')['props'];

        $shown = array_merge($props['partners'], $props['accreditations'], $props['clients']);

        foreach ($pinned as $partner) {
            $this->assertNotContains($partner->id, array_column($shown, 'id'),
                "«{$partner->name}» is pinned to a segment and is being shown site-wide.");
        }
    }

    #[Test]
    public function the_placeholder_partners_are_gone(): void
    {
        $this->assertSame(0, Partner::query()->whereIn('name', [
            'Events partner', 'Supply partner', 'Training partner',
            'Craft accreditation body', 'Quality accreditation body',
            'Government entity', 'Private-sector company',
        ])->count());
    }

    #[Test]
    public function reseeding_neither_duplicates_a_partner_nor_replaces_its_logo(): void
    {
        $before = Partner::query()->where('name', 'Alinma Bank')->sole()->getFirstMedia('logo');

        $this->seed(DemoContentSeeder::class);

        $after = Partner::query()->where('name', 'Alinma Bank')->sole()->getFirstMedia('logo');

        $this->assertSame(count(self::EXPECTED), $this->published()->count());
        $this->assertSame($before->id, $after->id);
    }

    #[Test]
    public function the_partners_page_renders_the_real_names(): void
    {
        $this->get('/ar/partners')
            ->assertOk()
            ->assertSee('مصرف الإنماء', false)
            ->assertSee('هيئة التراث', false)
            ->assertDontSee('شريك في الفعاليات', false);
    }

    #[Test]
    public function the_empty_groups_render_nothing_rather_than_an_empty_heading(): void
    {
        $response = $this->get('/ar/partners')->assertOk();

        // The section rows stay seeded so Amad Craft can fill them from the
        // panel, but until then the page must not head a group it cannot show.
        $this->assertSame([], $response->viewData('page')['props']['accreditations']);
        $this->assertSame([], $response->viewData('page')['props']['clients']);
    }
}
