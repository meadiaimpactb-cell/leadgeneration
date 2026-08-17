<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use App\Support\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The SEO screens in the admin panel (§9.1, §13, §14.1).
 *
 * §9.1 requires a panel usable "with no technical help needed". These tests
 * guard the two things that make that true here: every setting reaches a named
 * screen instead of a wall of database keys, and no setting can fall off the
 * panel just because a registry was not updated.
 */
class SeoAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);

        $this->admin = User::query()->create([
            'name' => 'SEO admin',
            'email' => 'seo@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);
    }

    #[Test]
    public function every_settings_screen_renders(): void
    {
        foreach (SettingsRegistry::SCREENS as $screen) {
            $this->actingAs($this->admin)
                ->get("/admin/settings/{$screen}")
                ->assertOk();
        }
    }

    #[Test]
    public function an_unknown_screen_is_a_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/settings/nonsense')->assertNotFound();
    }

    #[Test]
    public function no_setting_falls_off_the_panel(): void
    {
        // The registry decides which screen a key belongs to. A key it has
        // never heard of must still appear — on "advanced" — or a setting
        // added by a later migration becomes invisible and unmanageable, which
        // is the failure §14.1 exists to prevent.
        Setting::query()->create([
            'group' => 'tracking', 'key' => 'invented_later', 'value' => null, 'is_public' => false,
        ]);

        $keys = [];

        foreach (SettingsRegistry::SCREENS as $screen) {
            $props = $this->actingAs($this->admin)
                ->get("/admin/settings/{$screen}")
                ->assertOk()
                ->viewData('page')['props'];

            $keys = array_merge($keys, array_column($props['fields'], 'key'));
        }

        /*
         * The notifications wording is managed too, just not on a generic
         * settings screen: SettingsRegistry files those three keys under a
         * `notifications` screen, and that screen is NotificationController's
         * at /admin/integrations/notifications — deliberately, so the
         * addresses, their subscriptions and the mail's wording stay one
         * decision in one place. Walking SCREENS alone therefore reports them
         * as unreachable when they are not, so this screen is walked as well.
         * What the test guards is that every setting has somewhere to be
         * edited, and these do.
         */
        $keys = array_merge($keys, array_keys(
            $this->actingAs($this->admin)
                ->get('/admin/integrations/notifications')
                ->assertOk()
                ->viewData('page')['props']['template'],
        ));

        $all = Setting::query()->get()
            ->map(fn (Setting $s): string => "{$s->group}.{$s->key}")
            ->all();

        $this->assertSame([], array_diff($all, $keys), 'A setting reaches no screen.');
        $this->assertContains('tracking.invented_later', $keys);
    }

    #[Test]
    public function each_setting_appears_on_exactly_one_screen(): void
    {
        $seen = [];

        foreach (SettingsRegistry::SCREENS as $screen) {
            $props = $this->actingAs($this->admin)
                ->get("/admin/settings/{$screen}")->assertOk()
                ->viewData('page')['props'];

            foreach (array_column($props['fields'], 'key') as $key) {
                $this->assertArrayNotHasKey($key, $seen, "{$key} appears on two screens.");
                $seen[$key] = $screen;
            }
        }

        $this->assertNotEmpty($seen);
    }

    #[Test]
    public function the_robots_screen_shows_the_file_as_a_crawler_receives_it(): void
    {
        $props = $this->actingAs($this->admin)
            ->get('/admin/settings/robots')->assertOk()
            ->viewData('page')['props'];

        $this->assertNotNull($props['robotsPreview']);
        $this->assertSame($this->get('/robots.txt')->getContent(), $props['robotsPreview']);
        $this->assertSame(url('sitemap.xml'), $props['sitemapUrl']);
    }

    #[Test]
    public function the_search_console_code_is_emitted_as_a_verification_tag(): void
    {
        Setting::query()->where('group', 'tracking')->where('key', 'search_console')
            ->update(['value' => json_encode('token-from-search-console')]);

        app(Settings::class)->forget();

        $this->get('/ar')->assertOk()->assertSee(
            '<meta name="google-site-verification" content="token-from-search-console">',
            false,
        );
    }

    #[Test]
    public function no_verification_tag_is_emitted_when_the_code_is_unset(): void
    {
        $this->get('/ar')->assertOk()->assertDontSee('google-site-verification', false);
    }

    #[Test]
    public function keywords_reach_the_public_page_head(): void
    {
        $page = Page::query()->where('slug', 'products')->sole();
        $page->translationFor('ar')->update(['keywords' => 'هدايا مؤسسية']);

        $this->get('/ar/products')->assertOk()
            ->assertSee('name="keywords"', false)
            ->assertSee('هدايا مؤسسية', false);
    }

    #[Test]
    public function a_page_without_keywords_emits_no_keywords_tag(): void
    {
        $this->get('/ar/about')->assertOk()->assertDontSee('name="keywords"', false);
    }

    /**
     * A segment page has keywords of its own.
     *
     * `seo()` defaults keywords from the current *page* record, and a segment
     * page is not a `pages` row — so until `sector_translations.meta_keywords`
     * existed, the four pages written to be found by a searching procurement
     * officer were the only public pages with a title and a description and
     * nowhere to put their target terms.
     */
    #[Test]
    public function a_segment_page_carries_its_own_keywords(): void
    {
        $sector = Sector::query()->where('key', Sector::KEY_GOVERNMENT)->sole();
        $sector->translationFor('ar')->update(['meta_keywords' => 'دروع تكريم للجهات الحكومية']);

        $this->get('/ar/solutions/government')->assertOk()
            ->assertSee('name="keywords"', false)
            ->assertSee('دروع تكريم للجهات الحكومية', false);
    }

    /** The English page chases different terms — hence per translation. */
    #[Test]
    public function the_english_segment_page_carries_its_own_keywords(): void
    {
        $sector = Sector::query()->where('key', Sector::KEY_GOVERNMENT)->sole();
        $sector->translationFor('en')->update(['meta_keywords' => 'government corporate gifts']);

        $this->get('/en/solutions/government')->assertOk()
            ->assertSee('government corporate gifts', false);
    }

    /**
     * The panel writes '' for a field the editor never touched, and an empty
     * `<meta name="keywords" content="">` is worse than no tag: it states that
     * the page targets nothing.
     */
    #[Test]
    public function a_segment_without_keywords_emits_no_keywords_tag(): void
    {
        Sector::query()->where('key', Sector::KEY_GOVERNMENT)->sole()
            ->translationFor('ar')->update(['meta_keywords' => '']);

        $this->get('/ar/solutions/government')->assertOk()
            ->assertDontSee('name="keywords"', false);
    }

    /** Every segment offers the full trio in the panel, in every locale. */
    #[Test]
    public function the_panel_offers_the_whole_seo_trio_for_every_segment(): void
    {
        foreach (Sector::query()->pluck('id') as $id) {
            $props = $this->actingAs($this->admin)
                ->get("/admin/content/sectors/{$id}/edit")
                ->assertOk()
                ->viewData('page')['props'];

            foreach (['meta_title', 'meta_description', 'meta_keywords'] as $field) {
                $this->assertArrayHasKey($field, $props['meta']['fields'],
                    "Sector {$id} cannot be given a {$field} from the panel.");
            }

            foreach (['ar', 'en'] as $locale) {
                $this->assertArrayHasKey('meta_keywords', $props['record']['translations'][$locale],
                    "Sector {$id} has no meta_keywords field in {$locale}.");
            }
        }
    }

    #[Test]
    public function a_content_editor_cannot_reach_the_settings_screens(): void
    {
        $editor = User::query()->create([
            'name' => 'Editor', 'email' => 'ed@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'), 'is_active' => true,
        ]);
        $editor->assignRole(User::ROLE_EDITOR);

        $this->actingAs($editor)->get('/admin/settings/tracking')->assertForbidden();
    }
}
