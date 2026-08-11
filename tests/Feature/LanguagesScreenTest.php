<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Solution;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * 2.1 — the languages screen (§12).
 *
 * The English site's switch already governed `Locales`, the language toggle
 * and the sitemap; what it lacked was a home and a visible consequence. These
 * tests hold two things: that the screen drives the SAME setting rather than
 * a second flag of its own, and that the coverage figures are counted rather
 * than stored.
 */
class LanguagesScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([RolesSeeder::class, StructureSeeder::class]);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    #[Test]
    public function the_screen_reports_the_current_state_of_the_english_site(): void
    {
        $props = $this->actingAs($this->admin())
            ->get('/admin/languages')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['englishEnabled']);
        $this->assertSame('en', $props['secondary']);
        $this->assertNotEmpty($props['coverage']);
    }

    /**
     * The screen must not introduce a second flag. If it wrote its own key,
     * the site would keep serving English while the panel claimed otherwise.
     */
    #[Test]
    public function the_switch_drives_the_one_setting_the_site_already_reads(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/languages', ['englishEnabled' => false])
            ->assertRedirect();

        $this->assertFalse(
            app(Settings::class)->bool('site.english_enabled', true),
            'The switch did not write site.english_enabled — the site would still serve English.',
        );

        $this->assertSame(
            1,
            Setting::query()->where('group', 'site')->where('key', 'english_enabled')->count(),
            'A second row was created instead of updating the existing one.',
        );
    }

    #[Test]
    public function turning_english_off_from_this_screen_closes_the_english_sitemap(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/languages', ['englishEnabled' => false])
            ->assertRedirect();

        $this->get('/sitemap-en.xml')->assertNotFound();
    }

    /**
     * Counted, not stored. A percentage written down at save time starts
     * drifting the moment anyone edits a record.
     */
    #[Test]
    public function coverage_is_counted_from_the_translations_that_exist(): void
    {
        $admin = $this->admin();

        $solution = Solution::query()->create([
            'slug' => 'coverage-probe',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $solution->translations()->create(['locale' => 'ar', 'name' => 'حل']);

        $before = $this->rowFor($admin, 'solutions');

        $this->assertSame(1, $before['total']);
        $this->assertSame(0, $before['translated']);
        $this->assertSame(0, $before['percent']);

        $solution->translations()->create(['locale' => 'en', 'name' => 'Solution']);

        $after = $this->rowFor($admin, 'solutions');

        $this->assertSame(1, $after['translated']);
        $this->assertSame(100, $after['percent']);
    }

    /**
     * A content type nobody has created yet is not "0% translated" — showing
     * it as a failure sends someone looking for work that does not exist.
     */
    #[Test]
    public function an_empty_content_type_reports_no_percentage_at_all(): void
    {
        $row = $this->rowFor($this->admin(), 'stories');

        $this->assertSame(0, $row['total']);
        $this->assertNull($row['percent']);
    }

    /** @return array<string, mixed> */
    private function rowFor(User $admin, string $key): array
    {
        $coverage = $this->actingAs($admin)
            ->get('/admin/languages')
            ->assertOk()
            ->viewData('page')['props']['coverage'];

        foreach ($coverage as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        $this->fail("No coverage row for {$key}.");
    }
}
