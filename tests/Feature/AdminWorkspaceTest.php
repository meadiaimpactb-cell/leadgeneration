<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ProfileController;
use App\Models\Keyword;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\Seo\KeywordCoverage;
use App\Support\Brand;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The operator's own workspace: account, brand assets, and the keyword list.
 *
 * The keyword tests carry the most weight. This screen's honest claim is that
 * it reports coverage rather than performing SEO, so what has to hold is that
 * the report is accurate — a term shown as covered must really appear on a
 * page a visitor can reach, and a gap must really be a gap.
 */
class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, StructureSeeder::class, NavigationSeeder::class, DemoContentSeeder::class]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);

        $this->admin = User::query()->create([
            'name' => 'Amad Manager',
            'email' => 'manager@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);
    }

    // ---- Account ---------------------------------------------------------

    #[Test]
    public function anyone_signed_in_can_reach_their_own_account(): void
    {
        // Deliberately not gated on users.manage: a sales operator must be
        // able to change their own password without being able to touch
        // anyone else's account.
        $sales = User::query()->create([
            'name' => 'Sales', 'email' => 'sales@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'), 'is_active' => true,
        ]);
        $sales->assignRole(User::ROLE_SALES);

        $this->actingAs($sales)->get('/admin/profile')->assertOk();
        $this->actingAs($sales)->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function changing_the_password_requires_the_current_one(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/profile/password', [
                'current_password' => 'not-the-password',
                'password' => 'Str0ng!NewPassphrase',
                'password_confirmation' => 'Str0ng!NewPassphrase',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('correct-horse-battery-1!', $this->admin->fresh()->password));
    }

    #[Test]
    public function the_password_can_be_changed_with_the_current_one(): void
    {
        $this->actingAs($this->admin)
            ->put('/admin/profile/password', [
                'current_password' => 'correct-horse-battery-1!',
                'password' => 'Str0ng!NewPassphrase',
                'password_confirmation' => 'Str0ng!NewPassphrase',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Str0ng!NewPassphrase', $this->admin->fresh()->password));
    }

    // ---- Brand -----------------------------------------------------------

    #[Test]
    public function a_logo_can_be_replaced_and_removed(): void
    {
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'logo_light',
            'file' => UploadedFile::fake()->image('logo.png', 400, 120),
        ])->assertRedirect();

        $asset = app(Brand::class)->asset('logo_light');
        $this->assertNotNull($asset);

        $this->actingAs($this->admin)->delete("/admin/brand/{$asset['id']}")->assertRedirect();
        $this->assertNull(app(Brand::class)->asset('logo_light'));
    }

    #[Test]
    public function an_unknown_brand_slot_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'whatever',
            'file' => UploadedFile::fake()->image('x.png'),
        ])->assertSessionHasErrors('collection');
    }

    #[Test]
    public function a_non_image_cannot_be_uploaded_as_a_logo(): void
    {
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'logo_light',
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');
    }

    // ---- Keywords --------------------------------------------------------

    #[Test]
    public function a_pasted_list_is_split_on_lines_and_on_both_commas(): void
    {
        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'locale' => 'ar',
            'terms' => "هدايا مؤسسية\nتذكارات، حرف سعودية,corporate gifts",
        ])->assertRedirect();

        $this->assertSame(
            ['هدايا مؤسسية', 'تذكارات', 'حرف سعودية', 'corporate gifts'],
            Keyword::query()->orderBy('id')->pluck('term')->all(),
        );
    }

    #[Test]
    public function there_is_no_limit_and_re_pasting_creates_no_duplicates(): void
    {
        $first = collect(range(1, 60))->map(fn (int $i): string => "term-{$i}")->implode("\n");

        $this->actingAs($this->admin)->post('/admin/seo/keywords', ['locale' => 'ar', 'terms' => $first]);
        $this->assertSame(60, Keyword::query()->count());

        // Pasting an overlapping list is the normal case — an editor should
        // never have to de-duplicate by hand before saving.
        $this->actingAs($this->admin)->post('/admin/seo/keywords', [
            'locale' => 'ar', 'terms' => "term-1\nterm-2\nterm-61",
        ]);

        $this->assertSame(61, Keyword::query()->count());
    }

    #[Test]
    public function the_same_term_can_exist_in_both_languages(): void
    {
        $this->actingAs($this->admin)->post('/admin/seo/keywords', ['locale' => 'ar', 'terms' => 'gifts']);
        $this->actingAs($this->admin)->post('/admin/seo/keywords', ['locale' => 'en', 'terms' => 'gifts']);

        $this->assertSame(2, Keyword::query()->where('term', 'gifts')->count());
    }

    #[Test]
    public function a_term_in_a_page_title_is_reported_as_strong(): void
    {
        $page = Page::query()->where('slug', 'products')->sole();
        $title = $page->translationFor('ar')->title;

        Keyword::query()->create(['locale' => 'ar', 'term' => $title, 'is_active' => true]);

        $row = collect(app(KeywordCoverage::class)->report('ar'))->firstWhere('term', $title);

        $this->assertTrue($row['covered']);
        $this->assertTrue($row['strong']);
        $this->assertContains('products', array_column($row['pages'], 'slug'));
    }

    #[Test]
    public function a_term_nobody_wrote_is_reported_as_a_gap(): void
    {
        Keyword::query()->create([
            'locale' => 'ar', 'term' => 'عبارة لا يذكرها الموقع مطلقا', 'is_active' => true,
        ]);

        $row = collect(app(KeywordCoverage::class)->report('ar'))
            ->firstWhere('term', 'عبارة لا يذكرها الموقع مطلقا');

        $this->assertFalse($row['covered']);
        $this->assertSame([], $row['pages']);
    }

    #[Test]
    public function an_unpublished_page_never_counts_as_coverage(): void
    {
        // The whole report would be a lie otherwise: a draft cannot rank, so
        // reporting a term as covered by one tells the editor to stop working
        // on the exact thing they still need.
        $page = Page::query()->where('slug', 'about')->sole();
        $title = $page->translationFor('ar')->title;

        Keyword::query()->create(['locale' => 'ar', 'term' => $title, 'is_active' => true]);

        $covered = fn (): bool => collect(app(KeywordCoverage::class)->report('ar'))
            ->firstWhere('term', $title)['covered'];

        $this->assertTrue($covered());

        $page->forceFill(['status' => 'draft', 'published_at' => null])->save();

        $this->assertFalse($covered());
    }

    #[Test]
    public function coverage_is_reported_per_language(): void
    {
        // An Arabic term matching Arabic copy must not be reported as covered
        // on the English site, where that copy does not exist (§12).
        $title = Page::query()->where('slug', 'products')->sole()->translationFor('ar')->title;

        Keyword::query()->create(['locale' => 'en', 'term' => $title, 'is_active' => true]);

        $row = collect(app(KeywordCoverage::class)->report('en'))->firstWhere('term', $title);

        $this->assertFalse($row['covered']);
    }

    #[Test]
    public function a_keyword_can_be_deleted(): void
    {
        $keyword = Keyword::query()->create(['locale' => 'ar', 'term' => 'مؤقتة', 'is_active' => true]);

        $this->actingAs($this->admin)->delete("/admin/seo/keywords/{$keyword->id}")->assertRedirect();

        $this->assertSame(0, Keyword::query()->count());
    }

    #[Test]
    public function an_uploaded_logo_actually_reaches_the_pages(): void
    {
        // The gap this closes: the brand screen accepted uploads and stored
        // them correctly, but Logo.vue drew a hard-coded mask and never asked
        // whether one existed. The panel was taking files it had no way to
        // display, which is worse than not offering the upload at all.
        $this->assertStringNotContainsString('logo--file', $this->get('/ar')->getContent());

        // The dark-ground file, because the home page's header floats over the
        // navy hero and its footer is navy — that is where the mark actually
        // appears there. Uploading only the light-ground one would correctly
        // change nothing on this page, which is the mapping being asserted.
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'logo_dark',
            'file' => UploadedFile::fake()->image('client-logo-dark.png', 400, 120),
        ]);

        $url = app(Brand::class)->url('logo_dark');
        $this->assertNotNull($url);

        // Asserted on the props rather than on the rendered markup: the markup
        // is produced by the SSR process, so a test that greps it fails when
        // that process is down and reports a wiring bug that is not there.
        // Delivering the URL to the page is the server's half of the contract;
        // choosing the file by tone is Logo.vue's, and the tone mapping is
        // asserted in the next test.
        $props = $this->get('/ar')->assertOk()->viewData('page')['props'];

        $this->assertSame($url, $props['brand']['logo_dark']);
    }

    #[Test]
    public function each_logo_is_used_on_the_ground_it_was_uploaded_for(): void
    {
        // Two files rather than one recoloured file: a two-colour lockup
        // cannot be recoloured without flattening it, so the client uploads
        // the version made for each ground and the component picks by tone.
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'logo_light',
            'file' => UploadedFile::fake()->image('on-white.png', 400, 120),
        ]);
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'logo_dark',
            'file' => UploadedFile::fake()->image('on-navy.png', 400, 120),
        ]);

        $props = $this->get('/ar')->assertOk()->viewData('page')['props'];

        // Both reach the page as distinct URLs. Logo.vue picks between them by
        // tone: `white` — the treatment used over the navy hero, the footer and
        // the admin sidebar — takes the dark-ground file; every other tone
        // takes the light-ground one.
        $this->assertNotSame($props['brand']['logo_light'], $props['brand']['logo_dark']);
        $this->assertStringContainsString('on-white', $props['brand']['logo_light']);
        $this->assertStringContainsString('on-navy', $props['brand']['logo_dark']);
    }

    #[Test]
    public function an_uploaded_favicon_replaces_the_built_in_one(): void
    {
        // The built-in default is the set scripts/make-favicons.mjs generates
        // from the client's own icon. It was a hand-drawn favicon.svg once;
        // that file is gone, so this asserts the sizes actually shipped.
        $body = $this->get('/ar')->assertOk()->getContent();
        $this->assertStringContainsString('/favicon-32x32.png', $body);
        $this->assertStringContainsString('/favicon.ico', $body);

        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'favicon',
            'file' => UploadedFile::fake()->image('icon.png', 512, 512),
        ]);

        $body = $this->get('/ar')->assertOk()->getContent();

        // An upload replaces the whole built-in set, not just one line of it.
        $this->assertStringContainsString(app(Brand::class)->url('favicon'), $body);
        $this->assertStringNotContainsString('/favicon-32x32.png', $body);
        $this->assertStringNotContainsString('/favicon-16x16.png', $body);
    }

    #[Test]
    public function an_uploaded_share_image_becomes_the_og_image_default(): void
    {
        $this->actingAs($this->admin)->post('/admin/brand', [
            'collection' => 'og_image',
            'file' => UploadedFile::fake()->image('share.jpg', 1200, 630),
        ]);

        $this->get('/ar')->assertOk()
            ->assertSee('og:image', false)
            ->assertSee(app(Brand::class)->url('og_image'), false);
    }

    // ---- Tracking hints ---------------------------------------------------

    #[Test]
    public function tracking_fields_carry_an_example_and_a_shape_check(): void
    {
        $props = $this->actingAs($this->admin)
            ->get('/admin/settings/tracking')->assertOk()
            ->viewData('page')['props'];

        $fields = collect($props['fields'])->keyBy('key');

        // Pasting the whole snippet instead of the ID is the common failure,
        // and the pattern is what lets the panel say so at paste time.
        $this->assertSame('GTM-XXXXXXX', $fields['tracking.gtm_id']['placeholder']);
        $this->assertNotNull($fields['tracking.gtm_id']['pattern']);

        $this->assertSame(1, preg_match('/'.$fields['tracking.gtm_id']['pattern'].'/', 'GTM-ABC1234'));
        $this->assertSame(0, preg_match('/'.$fields['tracking.gtm_id']['pattern'].'/', 'the whole snippet GTM-ABC1234 pasted'));
        $this->assertSame(1, preg_match('/'.$fields['tracking.ga4_id']['pattern'].'/', 'G-ABCD123456'));
    }

    #[Test]
    public function the_brand_palette_is_read_only(): void
    {
        // §10.2 fixes the four colours and every contrast ratio is calculated
        // against them, so there is no endpoint that can change one.
        $props = $this->actingAs($this->admin)
            ->get('/admin/brand')->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(4, $props['palette']);
        $this->assertSame('#002546', $props['palette'][0]['hex']);

        $this->assertSame([], array_filter(
            Setting::query()->pluck('key')->all(),
            fn (string $key): bool => str_contains($key, 'colour') || str_contains($key, 'color'),
        ));
    }

    // ---- The shell -------------------------------------------------------

    #[Test]
    public function the_shell_names_the_role_the_operator_is_acting_as(): void
    {
        // Four roles see four different sidebars. A panel that does not say
        // which one is looking turns "you lack the permission" into "the panel
        // is broken" — the support call this prevents.
        $props = $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->viewData('page')['props'];

        $this->assertContains('super-admin', collect($props['auth']['user']['roles'])->all());

        // And the panel has a word for it, rather than echoing the key.
        $this->assertNotSame('admin.role_super_admin', __('admin.role_super_admin'));
    }

    #[Test]
    public function leaving_the_panel_never_needs_a_menu_to_be_opened(): void
    {
        // Sign-out and "view the site" are rendered in the bar itself, not
        // inside the account dropdown. Hiding the way out of an admin panel
        // behind a click is a small cruelty, and on a shared machine a risk.
        $body = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('topbar__out', $body);
        $this->assertStringContainsString('topbar__visit', $body);
    }

    #[Test]
    public function every_navigation_entry_carries_an_icon(): void
    {
        // Icons are for recognition: a sidebar of twenty-six Arabic labels is
        // read letter by letter every visit without them. One missing icon
        // breaks the column alignment of all the others, so this counts rather
        // than spot-checks.
        $body = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $links = $this->sidebarLinks($body);
        $icons = substr_count($body, 'class="ico"');

        $this->assertGreaterThan(20, count($links));
        $this->assertGreaterThanOrEqual(count($links), $icons);
    }

    #[Test]
    public function an_editor_is_not_offered_screens_they_cannot_open(): void
    {
        // One render per test, deliberately. Two SSR renders inside a single
        // test return the first one's markup for both, so a comparison across
        // two roles in one test compares a page with itself and passes or
        // fails for reasons that have nothing to do with the sidebar.
        $editor = User::query()->create([
            'name' => 'Editor', 'email' => 'editor@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'), 'is_active' => true,
        ]);
        $editor->assignRole(User::ROLE_EDITOR);

        $links = $this->sidebarLinks($this->actingAs($editor)->get('/admin')->assertOk()->getContent());

        $this->assertContains('/admin/pages', $links);
        $this->assertContains('/admin/content/solutions', $links);

        // The panel is not the protection — every route is enforced by a
        // policy (§9.2) — but offering an editor a settings link the server
        // will refuse is a promise the panel then breaks.
        $this->assertNotContains('/admin/settings/tracking', $links);
        $this->assertNotContains('/admin/users', $links);
        $this->assertNotContains('/admin/leads', $links);
    }

    #[Test]
    public function a_super_admin_is_offered_everything(): void
    {
        $links = $this->sidebarLinks(
            $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent()
        );

        foreach (['/admin/pages', '/admin/leads', '/admin/users', '/admin/brand',
            '/admin/settings/tracking', '/admin/seo/keywords'] as $href) {
            $this->assertContains($href, $links);
        }
    }

    // ---- Profile page ----------------------------------------------------

    #[Test]
    public function every_profile_tab_is_its_own_url(): void
    {
        // Tabs as real URLs, not client-side toggles: a tab can then be
        // linked, bookmarked, and returned to after a save — which is the
        // whole point of not making someone reopen a menu each time.
        foreach (ProfileController::TABS as $tab) {
            $this->actingAs($this->admin)->get("/admin/profile/{$tab}")->assertOk();
        }

        $this->actingAs($this->admin)->get('/admin/profile')->assertOk();
        $this->actingAs($this->admin)->get('/admin/profile/invented')->assertNotFound();
    }

    #[Test]
    public function the_profile_header_carries_identity_not_just_a_form(): void
    {
        $props = $this->actingAs($this->admin)->get('/admin/profile')->assertOk()
            ->viewData('page')['props'];

        $this->assertSame($this->admin->name, $props['profile']['name']);
        $this->assertSame($this->admin->email, $props['profile']['email']);
        $this->assertContains('super-admin', $props['profile']['roles']);
        $this->assertNotNull($props['profile']['createdAt']);

        // Said plainly rather than implied: §9.2 requires 2FA for super-admin
        // and it is not built, so the screen reports false instead of showing
        // a switch that does nothing.
        $this->assertFalse($props['profile']['twoFactor']);
    }

    #[Test]
    public function a_photo_and_a_cover_can_be_uploaded_and_removed(): void
    {
        $this->actingAs($this->admin)->post('/admin/profile/image', [
            'collection' => 'avatar',
            'file' => UploadedFile::fake()->image('me.png', 400, 400),
        ])->assertRedirect();

        $this->actingAs($this->admin)->post('/admin/profile/image', [
            'collection' => 'cover',
            'file' => UploadedFile::fake()->image('banner.png', 1600, 400),
        ])->assertRedirect();

        $user = $this->admin->fresh();
        $this->assertNotNull($user->avatarUrl());
        $this->assertNotNull($user->coverUrl());

        $this->actingAs($this->admin)->delete('/admin/profile/image/avatar')->assertRedirect();
        $this->assertNull($this->admin->fresh()->avatarUrl());
    }

    #[Test]
    public function an_operator_can_only_replace_their_own_picture(): void
    {
        // This route is open to every role, so it must never accept a target
        // from the payload — otherwise a sales operator could replace the
        // super-admin's photograph.
        $sales = User::query()->create([
            'name' => 'Sales', 'email' => 'sales2@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'), 'is_active' => true,
        ]);
        $sales->assignRole(User::ROLE_SALES);

        $this->actingAs($sales)->post('/admin/profile/image', [
            'collection' => 'avatar',
            'user_id' => $this->admin->id,
            'file' => UploadedFile::fake()->image('theirs.png', 200, 200),
        ])->assertRedirect();

        $this->assertNotNull($sales->fresh()->avatarUrl());
        $this->assertNull($this->admin->fresh()->avatarUrl());
    }

    #[Test]
    public function only_an_image_can_become_a_profile_picture(): void
    {
        $this->actingAs($this->admin)->post('/admin/profile/image', [
            'collection' => 'avatar',
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->admin)->post('/admin/profile/image', [
            'collection' => 'somewhere-else',
            'file' => UploadedFile::fake()->image('x.png'),
        ])->assertSessionHasErrors('collection');
    }

    #[Test]
    public function the_uploaded_photo_replaces_the_initials_in_the_panel_bar(): void
    {
        $this->actingAs($this->admin)->post('/admin/profile/image', [
            'collection' => 'avatar',
            'file' => UploadedFile::fake()->image('me.png', 400, 400),
        ]);

        $props = $this->actingAs($this->admin)->get('/admin')->assertOk()
            ->viewData('page')['props'];

        $this->assertSame($this->admin->fresh()->avatarUrl(), $props['auth']['user']['avatar']);
    }

    #[Test]
    public function the_account_column_carries_every_destination_the_dropdown_did(): void
    {
        // The bar used to unfold a menu holding these. Every one of them then
        // had to be reopened to reach the next, so they now stand in a column
        // inside the profile — the point of the column, and the thing most
        // likely to be undone by a later "tidy up".
        // Read from the shared `workspace` prop: the column is rendered by a
        // frame every one of these screens sits inside, so its data is shared
        // once rather than returned by each controller.
        $props = $this->actingAs($this->admin)->get('/admin/profile')->assertOk()
            ->viewData('page')['props'];

        $hrefs = array_column($props['workspace']['shortcuts'], 'href');

        foreach (['/admin/brand', '/admin/settings/site', '/admin/settings/contact',
            '/admin/settings/tracking', '/admin/seo/keywords', '/admin/users'] as $href) {
            $this->assertContains($href, $hrefs);
        }

        // And each carries a name and a line of explanation, not a bare link.
        foreach ($props['workspace']['shortcuts'] as $item) {
            $this->assertNotSame('', trim($item['label']));
            $this->assertStringNotContainsString('admin.shortcut_', $item['label']);
            $this->assertStringNotContainsString('admin.shortcut_', $item['hint']);
        }
    }

    #[Test]
    public function the_account_column_offers_nothing_the_operator_cannot_open(): void
    {
        $sales = User::query()->create([
            'name' => 'Sales', 'email' => 'sales3@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'), 'is_active' => true,
        ]);
        $sales->assignRole(User::ROLE_SALES);

        $props = $this->actingAs($sales)->get('/admin/profile')->assertOk()
            ->viewData('page')['props'];

        $hrefs = array_column($props['workspace']['shortcuts'], 'href');

        $this->assertNotContains('/admin/users', $hrefs);
        $this->assertNotContains('/admin/settings/tracking', $hrefs);

        // The four profile tabs stay: they are about this person, not the site.
        $this->assertSame(ProfileController::TABS, array_column($props['workspace']['tabs'], 'key'));
    }

    #[Test]
    public function the_panel_bar_holds_no_dropdown(): void
    {
        // A menu that unfolds from the bar hides destinations behind a hover
        // and closes on every navigation. The chip is a plain link now, and
        // this test is what stops one being reintroduced.
        $body = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-haspopup', $body);
        $this->assertStringNotContainsString('account__menu', $body);
        $this->assertMatchesRegularExpression('/<a class="account"[^>]*href="\/admin\/profile"/', $body);
    }

    #[Test]
    public function the_column_survives_following_one_of_its_own_links(): void
    {
        // The failure this replaces: every shortcut navigated to a screen with
        // no column, so reaching the second one meant going back to the
        // profile first. The frame now renders on each of them.
        foreach (['/admin/profile', '/admin/profile/appearance', '/admin/brand',
            '/admin/settings/site', '/admin/settings/tracking', '/admin/seo/keywords'] as $path) {
            $body = $this->actingAs($this->admin)->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('class="tabs"', $body, "{$path} lost the account column");
            $this->assertStringContainsString('class="hero"', $body, "{$path} lost the header");
        }
    }

    #[Test]
    public function the_bare_profile_path_marks_the_details_tab(): void
    {
        // One render, deliberately: two SSR renders inside a single test
        // return the first one's markup for both, so a loop over several
        // screens would assert the first screen against every expectation.
        //
        // This is the case worth pinning anyway — /admin/profile renders the
        // details tab, so that is what has to light up. Without the special
        // case the column arrives with nothing selected, which reads as
        // "nothing is loaded yet".
        $body = $this->actingAs($this->admin)->get('/admin/profile')->assertOk()->getContent();

        preg_match('/<a class="tab is-current"[^>]*href="([^"]+)"/', $body, $match);

        $this->assertSame('/admin/profile/details', $match[1] ?? null);
    }

    /**
     * The hrefs actually rendered in the sidebar.
     *
     * Matched as anchors rather than counted as a substring: the class name
     * occurs elsewhere in the payload, so counting it reported a filtered
     * sidebar and an unfiltered one as the same size.
     *
     * @return list<string>
     */
    private function sidebarLinks(string $body): array
    {
        preg_match_all('/<a[^>]*class="side__link[^"]*"[^>]*href="([^"]+)"/', $body, $matches);

        return $matches[1];
    }
}
