<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\Solution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The audience segments, as a field on a solution.
 *
 * "القطاعات" stopped being a content type of its own in the sidebar. The
 * records did not go anywhere — each segment still has a public page and its
 * own editor — but a solution can now say which audiences it is offered to,
 * and that is what makes the merge real rather than a menu edit.
 */
class SolutionSegmentTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function solution(): Solution
    {
        $solution = Solution::query()->create([
            'slug' => 'test-solution',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $solution->translations()->create(['locale' => 'ar', 'name' => 'حل للاختبار']);

        return $solution;
    }

    #[Test]
    public function the_editor_offers_every_segment_as_a_choice(): void
    {
        $props = $this->actingAs($this->admin())
            ->get("/admin/content/solutions/{$this->solution()->id}/edit")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertArrayHasKey('sectors', $props['meta']['taxonomies']);

        $labels = array_column($props['taxonomyOptions']['sectors'], 'label');

        $this->assertCount(Sector::query()->count(), $labels);
        $this->assertNotEmpty($labels);
    }

    #[Test]
    public function chosen_segments_are_saved_and_come_back(): void
    {
        $admin = $this->admin();
        $solution = $this->solution();

        $chosen = Sector::query()->orderBy('sort_order')->limit(2)->pluck('id')->all();

        $this->actingAs($admin)
            ->patch("/admin/content/solutions/{$solution->id}", [
                'active' => true,
                'attributes' => ['slug' => 'test-solution', 'icon' => 'gifts'],
                'translations' => ['ar' => ['name' => 'حل للاختبار']],
                'taxonomies' => ['sectors' => $chosen],
            ])
            ->assertRedirect();

        $this->assertSame($chosen, $solution->fresh()->sectors()->get()->modelKeys());

        // And the editor loads them back as the current selection.
        $props = $this->actingAs($admin)
            ->get("/admin/content/solutions/{$solution->id}/edit")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame($chosen, $props['taxonomyValues']['sectors']);
    }

    /**
     * An empty array means "cleared" and must be honoured. Treating it as
     * "nothing submitted" is the classic way a de-selection silently fails.
     */
    #[Test]
    public function clearing_every_segment_is_honoured(): void
    {
        $admin = $this->admin();
        $solution = $this->solution();

        $solution->sectors()->sync(Sector::query()->pluck('id')->all());
        $this->assertNotEmpty($solution->fresh()->sectors()->get());

        $this->actingAs($admin)
            ->patch("/admin/content/solutions/{$solution->id}", [
                'active' => true,
                'attributes' => ['slug' => 'test-solution', 'icon' => 'gifts'],
                'translations' => ['ar' => ['name' => 'حل للاختبار']],
                'taxonomies' => ['sectors' => []],
            ])
            ->assertRedirect();

        $this->assertEmpty($solution->fresh()->sectors()->get());
    }

    /**
     * The merge must not have cost the segments their own pages: they are
     * still full records, not labels.
     */
    #[Test]
    public function every_segment_still_has_its_own_public_page(): void
    {
        $this->admin();

        foreach (['government', 'companies', 'partners', 'artisans'] as $slug) {
            $this->assertTrue(
                Sector::query()->where('slug', $slug)->exists(),
                "The {$slug} segment record is missing.",
            );
        }
    }
}
