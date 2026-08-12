<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImpactMetric;
use App\Models\Partner;
use App\Models\Sector;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The trust strip and the number band on a segment page.
 *
 * §11.2 reserves both, and both render from data — so with nothing pinned to
 * a sector they were absent rather than empty and nobody could judge them.
 * DemoExtrasSeeder fills them with stand-ins.
 *
 * These guard the two things that make stand-ins safe to ship to a staging
 * site: they render, and they retire themselves the moment a real row exists.
 */
class SegmentTrustBlocksTest extends TestCase
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
            DemoExtrasSeeder::class,
        ]);
    }

    private function government(): Sector
    {
        return Sector::query()->where('key', Sector::KEY_GOVERNMENT)->sole();
    }

    #[Test]
    public function both_blocks_reach_the_page(): void
    {
        $props = $this->get('/ar/solutions/government')->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(4, $props['impact'], 'The number band has nothing to show.');
        $this->assertCount(5, $props['clients'], 'The trust strip has nothing to show.');

        // Five is the threshold PartnersLogos animates at — the marquee is
        // half of what the strip is being looked at for.
        $this->assertGreaterThanOrEqual(5, count($props['clients']));
    }

    #[Test]
    public function the_figures_borrow_the_labels_the_client_already_wrote(): void
    {
        $borrowed = ImpactMetric::query()->whereNull('sector_id')
            ->with('translations')->get()
            ->flatMap(fn (ImpactMetric $m): array => $m->translations->pluck('label')->all())
            ->filter()->all();

        foreach ($this->government()->impactMetrics()->with('translations')->get() as $metric) {
            foreach ($metric->translations as $translation) {
                $this->assertContains($translation->label, $borrowed,
                    "«{$translation->label}» is wording this seeder invented rather than reused.");
            }
        }
    }

    /**
     * The stand-in logos name nobody.
     *
     * DemoContentSeeder::partners() documents why: the five real
     * organisations on this site are published as partners, so filing one
     * under "client" asserts a relationship it has not published itself.
     */
    #[Test]
    public function no_real_organisation_is_presented_as_a_client(): void
    {
        $published = Partner::query()->where('type', Partner::TYPE_PARTNER)
            ->with('translations')->get();

        $clientNames = Partner::query()->where('type', Partner::TYPE_CLIENT)
            ->with('translations')->get()
            ->flatMap(fn (Partner $p): array => array_merge(
                [$p->name],
                $p->translations->pluck('display_name')->all(),
            ))->filter()->all();

        foreach ($published as $partner) {
            $this->assertNotContains($partner->name, $clientNames);

            foreach ($partner->translations as $translation) {
                $this->assertNotContains($translation->display_name, $clientNames,
                    "«{$translation->display_name}» is published as a partner and is being shown as a client.");
            }
        }
    }

    #[Test]
    public function real_client_logos_retire_the_stand_ins(): void
    {
        $sector = $this->government();

        $this->assertSame(5, $sector->clients()->count());

        $real = Partner::query()->create([
            'name' => 'A real client the panel added',
            'type' => Partner::TYPE_CLIENT,
            'sector_id' => $sector->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->seed(DemoExtrasSeeder::class);

        $this->assertSame(0, Partner::query()->where('name', 'like', 'Placeholder client%')->count(),
            'The stand-in logos outlived the real ones arriving.');

        $this->assertModelExists($real);
    }

    /** Per block: real numbers do not retire the logos, or the other way round. */
    #[Test]
    public function a_real_metric_retires_only_the_stand_in_metrics(): void
    {
        $sector = $this->government();

        ImpactMetric::query()->create([
            'key' => 'entities-served-government',
            'sector_id' => $sector->id,
            'value_numeric' => 12,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->seed(DemoExtrasSeeder::class);

        $this->assertSame(0, ImpactMetric::query()->where('key', 'like', 'placeholder-%')->count());
        $this->assertSame(5, $sector->clients()->count(), 'The logos were retired by a metric.');
    }

    #[Test]
    public function re_running_the_seeder_neither_duplicates_nor_churns(): void
    {
        $sector = $this->government();
        $logo = $sector->clients()->with(['media', 'mediaAttachments.media'])->first()?->mediaFor('logo');

        $this->seed(DemoExtrasSeeder::class);

        $this->assertSame(5, $sector->clients()->count());
        $this->assertSame(4, $sector->impactMetrics()->count());

        // Same media row, not a fresh copy of the same picture on disk.
        $again = $sector->clients()->with(['media', 'mediaAttachments.media'])->first()?->mediaFor('logo');
        $this->assertSame($logo?->id, $again?->id);
    }
}
