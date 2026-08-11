<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\UpcomingScreenController;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sidebar's approved structure, held in place.
 *
 * The panel's map used to be written inline in AdminLayout.vue, where the
 * only way to check it was to read a template — and it drifted. It now comes
 * from one module, and these are the guarantees that module has to keep:
 * every screen it names exists, the groups stay in the agreed order, and the
 * entries that were deliberately taken out do not come back.
 */
class AdminSidebarStructureTest extends TestCase
{
    use RefreshDatabase;

    private function source(): string
    {
        $path = base_path('resources/js/admin/navigation.js');

        $this->assertFileExists($path, 'The sidebar must be defined in one module.');

        return (string) file_get_contents($path);
    }

    private function admin(): User
    {
        $this->seed(RolesSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    #[Test]
    public function the_panel_map_lives_in_exactly_one_module(): void
    {
        $this->source();

        // AdminLayout resolves labels; it must no longer carry the list.
        $layout = (string) file_get_contents(base_path('resources/js/Layouts/AdminLayout.vue'));

        $this->assertStringContainsString('NAV_GROUPS', $layout);
        $this->assertStringNotContainsString("href: '/admin/leads'", $layout,
            'The sidebar is defined in AdminLayout again — it belongs in resources/js/admin/navigation.js.');
    }

    #[Test]
    public function the_groups_appear_in_the_approved_order(): void
    {
        $source = $this->source();

        $expected = ['overview', 'content', 'campaigns', 'clients', 'visibility', 'system', 'settings'];

        preg_match_all("/key: '([a-z]+)'/", $source, $matches);

        $this->assertSame($expected, $matches[1],
            'The sidebar groups are not in the approved order.');
    }

    /**
     * The criterion is the NAME, not the reachability.
     *
     * "القطاعات" is gone as a content type of its own; the same records are
     * now "شرائح الحلول", listed under Solutions. An earlier version of this
     * test asserted the screen was absent from the sidebar entirely, which
     * would have left a live editor that only someone reading the source
     * could find — the opposite of what a panel is for.
     */
    #[Test]
    public function the_old_sectors_label_is_gone_from_the_panel(): void
    {
        $layout = (string) file_get_contents(base_path('resources/js/admin/navigation.js'));

        $this->assertStringNotContainsString("'admin.sectors'", $layout,
            'The old "القطاعات" label is back in the sidebar.');
    }

    /**
     * Merged, not hidden. The four public segment pages are Sector records,
     * so their editor has to be one click from the menu.
     */
    #[Test]
    public function the_segment_editor_is_reachable_from_the_menu(): void
    {
        $source = $this->source();

        $this->assertStringContainsString("label: 'admin.solution_segments', href: '/admin/content/sectors'", $source,
            'The segment editor must be a sidebar entry, nested under Solutions.');

        // Nested, not another top-level content type.
        $this->assertMatchesRegularExpression(
            "/href: '\/admin\/content\/sectors'[^}]*sub: true/",
            $source,
            'The segment editor must be marked as a sub-entry of Solutions.',
        );

        $this->actingAs($this->admin())
            ->get('/admin/content/sectors')
            ->assertOk();
    }

    #[Test]
    public function every_screen_the_sidebar_names_actually_opens(): void
    {
        $source = $this->source();
        $admin = $this->admin();

        preg_match_all("/href: '([^']+)'/", $source, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $href) {
            $this->actingAs($admin)
                ->get($href)
                ->assertSuccessful("The sidebar links to {$href}, which does not open.");
        }
    }

    /**
     * A placeholder that says nothing is worse than no placeholder: the
     * operator cannot tell a screen still to be built from a broken one.
     */
    #[Test]
    public function every_upcoming_screen_states_what_it_will_do(): void
    {
        $labels = require base_path('resources/lang/ar/admin.php');

        foreach (UpcomingScreenController::keys() as $key) {
            $this->assertArrayHasKey("upcoming.{$key}_title", $labels);
            $this->assertArrayHasKey("upcoming.{$key}_body", $labels);
            $this->assertNotEmpty($labels["upcoming.{$key}_body"]);
        }
    }

    #[Test]
    public function both_admin_languages_carry_the_same_keys(): void
    {
        $ar = require base_path('resources/lang/ar/admin.php');
        $en = require base_path('resources/lang/en/admin.php');

        $this->assertSame([], array_diff(array_keys($ar), array_keys($en)),
            'Keys present in Arabic and missing in English render as raw keys.');
        $this->assertSame([], array_diff(array_keys($en), array_keys($ar)));
    }
}
