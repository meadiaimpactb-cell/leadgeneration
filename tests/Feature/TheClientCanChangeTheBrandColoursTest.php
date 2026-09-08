<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Color;
use App\Support\Palette;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The four identity colours are the client's to change (§19, §23).
 *
 * They were fixed in the token layer, and §10.2's reason for fixing them was a
 * good one: every contrast guarantee in §10.8 was calculated against those
 * exact values. This suite is what lets that reason be answered rather than
 * ignored — it holds the derivation to the guarantees, and it holds the
 * approved identity to the exact values §23 approved.
 */
class TheClientCanChangeTheBrandColoursTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function palette(): Palette
    {
        app(Settings::class)->forget();

        return app(Palette::class);
    }

    private function choose(array $colours): void
    {
        foreach ($colours as $family => $hex) {
            Setting::query()->updateOrCreate(
                ['group' => 'brand', 'key' => "colour.{$family}"],
                ['value' => $hex, 'is_public' => false],
            );
        }

        app(Settings::class)->forget();
    }

    /**
     * Out of the box the site is the approved identity, to the byte.
     *
     * The derivation reproduces the hand-drawn shades to within four parts in
     * 255, which is invisible — and "invisible" is not "unchanged". A reviewer
     * holding this build against the identity document is owed the second one,
     * so an untouched family serves the approved values rather than derived
     * ones. This is the test that says so.
     */
    #[Test]
    public function an_untouched_palette_is_the_approved_identity(): void
    {
        $tokens = $this->palette()->tokens();

        $approved = [
            '--navy-900' => '#002546',
            '--lavender-500' => '#8685D8',
            '--orange-500' => '#D7653B',
            '--gold-400' => '#DCAD75',
            '--navy-950' => '#001A31',
            '--navy-800' => '#06345C',
            '--navy-700' => '#0B4370',
            '--navy-100' => '#E3EAF1',
            '--lavender-700' => '#5F5EBE',
            '--action-600' => '#B4522C',
            '--action-700' => '#9C4525',
            '--gold-100' => '#F6ECDD',
            '--sand' => '#F3EEE7',
            '--paper-warm' => '#FBF8F3',
            '--placeholder-warm' => '#E9E1D6',
        ];

        foreach ($approved as $token => $hex) {
            $this->assertSame($hex, $tokens[$token], "{$token} is not the value §23 approved.");
        }

        $this->assertTrue($this->palette()->isIdentity());
    }

    /** A colour a family does not own is not disturbed by that family changing. */
    #[Test]
    public function changing_one_colour_leaves_the_other_three_ramps_alone(): void
    {
        $this->choose(['navy' => '#1B3A2F']);

        $tokens = $this->palette()->tokens();

        $this->assertSame('#1B3A2F', $tokens['--navy-900']);
        $this->assertNotSame('#06345C', $tokens['--navy-800'], 'The navy ramp did not follow navy.');

        // Untouched families, byte for byte.
        $this->assertSame('#D7653B', $tokens['--orange-500']);
        $this->assertSame('#B4522C', $tokens['--action-600']);
        $this->assertSame('#DCAD75', $tokens['--gold-400']);
        $this->assertSame('#FBF8F3', $tokens['--paper-warm']);
    }

    /**
     * The guarantee, through the palette rather than through the maths.
     *
     * `Color::legibleOn` is unit-tested; this checks the palette actually
     * applies it to the two shades that need it, for palettes chosen to be
     * awkward — a near-white orange has no legible shade until it is darkened
     * a long way.
     *
     * @param  array<string, string>  $colours
     */
    #[Test]
    #[DataProvider('awkwardPalettes')]
    public function the_two_guaranteed_shades_hold_whatever_is_chosen(array $colours): void
    {
        $this->choose($colours);

        $tokens = $this->palette()->tokens();
        $white = Color::fromHex('#FFFFFF');
        $ground = Color::fromHex($tokens['--paper-warm']);

        $this->assertGreaterThanOrEqual(
            4.5,
            round(Color::fromHex($tokens['--lavender-700'])->contrast($ground), 2),
            'Link text is not legible on the page ground.',
        );

        $this->assertGreaterThanOrEqual(
            4.5,
            round($white->contrast(Color::fromHex($tokens['--action-600'])), 2),
            'White is not legible on the action colour.',
        );
    }

    /**
     * The page ground stays a page ground.
     *
     * Derived as an offset from the premium colour, a deep amber dragged the
     * ground down with it and the whole site went mid-tone — every body-text
     * ratio on every page falling at once, from one save. The four warm
     * neutrals are pinned to a lightness for exactly this reason.
     *
     * @param  array<string, string>  $colours
     */
    #[Test]
    #[DataProvider('awkwardPalettes')]
    public function the_page_ground_stays_pale_whatever_is_chosen(array $colours): void
    {
        $this->choose($colours);

        $ground = Color::fromHex($this->palette()->tokens()['--paper-warm']);

        $this->assertGreaterThanOrEqual(
            10.0,
            round(Color::fromHex('#10161C')->contrast($ground), 2),
            "Body text is not legible on the page ground ({$ground->toHex()}).",
        );
    }

    /** @return array<string, array{array<string, string>}> */
    public static function awkwardPalettes(): array
    {
        return [
            'a forest identity' => [['navy' => '#1B3A2F', 'gold' => '#C9A227', 'orange' => '#C8452F', 'lavender' => '#7C5BD6']],
            'everything dark' => [['navy' => '#101010', 'gold' => '#3B2E12', 'orange' => '#4A1D10', 'lavender' => '#241F4A']],
            'everything pale' => [['navy' => '#DDE7F0', 'gold' => '#FFF3C4', 'orange' => '#FFD9C9', 'lavender' => '#E4E3FB']],
            'fully saturated' => [['navy' => '#0000FF', 'gold' => '#FFFF00', 'orange' => '#FF0000', 'lavender' => '#FF00FF']],
            'no colour at all' => [['navy' => '#000000', 'gold' => '#FFFFFF', 'orange' => '#808080', 'lavender' => '#404040']],
        ];
    }

    /**
     * A value that is not a colour never reaches the stylesheet.
     *
     * One malformed declaration takes the whole `:root` block down in some
     * browsers, which turns a single bad character in one row into a site with
     * no colours at all. So a row that is not six hex digits is treated as
     * absent rather than rendered.
     */
    #[Test]
    public function a_row_that_is_not_a_colour_falls_back_to_the_identity(): void
    {
        $this->choose(['navy' => 'red; } body { display: none } :root {']);

        $palette = $this->palette();

        $this->assertSame('#002546', $palette->tokens()['--navy-900']);
        $this->assertStringNotContainsString('display', $palette->css());
        $this->assertStringNotContainsString('}', substr($palette->css(), 0, -1));
    }

    #[Test]
    public function the_css_block_carries_the_channels_the_tints_are_built_from(): void
    {
        $css = $this->palette()->css();

        $this->assertStringContainsString('--navy-rgb:0 37 70', $css);
        $this->assertStringContainsString('--gold-rgb:220 173 117', $css);
    }

    /* ------------------------------------------------------------------
       The screen and the endpoint
       ------------------------------------------------------------------ */

    #[Test]
    public function the_brand_screen_offers_the_colours_and_what_they_derive(): void
    {
        $props = $this->actingAs($this->admin())
            ->get('/admin/brand')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(Palette::IDENTITY, $props['palette']);
        $this->assertSame(Palette::IDENTITY, $props['identity']);
        $this->assertSame('#06345C', $props['derived']['--navy-800']);
        $this->assertNotEmpty($props['contrast']);

        foreach ($props['contrast'] as $row) {
            $this->assertTrue($row['passes'], "The approved identity fails {$row['pair']}.");
        }
    }

    #[Test]
    public function saving_a_palette_changes_what_every_page_serves(): void
    {
        $before = $this->get('/ar')->assertOk()->getContent();
        $this->assertStringContainsString('--navy-900:#002546', $before);

        $this->actingAs($this->admin())
            ->put('/admin/brand/palette', [
                'navy' => '#1B3A2F',
                'lavender' => '#8685D8',
                'orange' => '#D7653B',
                'gold' => '#DCAD75',
            ])
            ->assertRedirect();

        $after = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('--navy-900:#1B3A2F', $after);
        // The whole ramp moved with it, not only the one value.
        $this->assertStringNotContainsString('--navy-800:#06345C', $after);
        // And the browser chrome followed.
        $this->assertStringContainsString('content="#1B3A2F"', $after);
    }

    /**
     * The installed shortcut takes the colour too.
     *
     * The manifest was a static file in public/ with the identity's navy
     * written into it twice. Nothing would have told anyone it was stale: it
     * is read once, by a browser, when somebody adds the site to a home
     * screen — so the splash screen would have kept flashing the old colour
     * long after the site had stopped using it.
     */
    #[Test]
    public function the_web_manifest_carries_the_chosen_colour(): void
    {
        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertSee('"theme_color": "#002546"', false);

        $this->choose(['navy' => '#1B3A2F']);

        $manifest = json_decode($this->get('/site.webmanifest')->assertOk()->getContent(), true);

        $this->assertSame('#1B3A2F', $manifest['theme_color']);
        $this->assertSame('#1B3A2F', $manifest['background_color']);
        // And it still says what the site is called, from the same rows the
        // footer reads — the other half of what was frozen into the file.
        $this->assertNotEmpty($manifest['name']);
    }

    /**
     * Going back to the identity leaves nothing behind.
     *
     * A reset that rewrites §23's own values into four rows would work, and
     * would leave a fifth copy of the approved identity in the database — one
     * more thing to disagree with the token layer, and no way to answer "which
     * of these has the client actually changed".
     */
    #[Test]
    public function returning_to_the_identity_removes_the_rows(): void
    {
        $this->choose(['navy' => '#1B3A2F']);

        $this->assertDatabaseHas('settings', ['group' => 'brand', 'key' => 'colour.navy']);

        $this->actingAs($this->admin())
            ->put('/admin/brand/palette', Palette::IDENTITY)
            ->assertRedirect();

        $this->assertDatabaseMissing('settings', ['group' => 'brand', 'key' => 'colour.navy']);
        $this->assertTrue($this->palette()->isIdentity());
    }

    /** The panel is not the only place this must be right — so is the panel. */
    #[Test]
    public function the_panel_is_themed_by_the_same_block(): void
    {
        $this->choose(['navy' => '#1B3A2F']);

        $this->actingAs($this->admin())
            ->get('/admin/brand')
            ->assertOk()
            ->assertSee('--navy-900:#1B3A2F', false);
    }

    #[Test]
    public function it_refuses_a_value_that_is_not_a_six_digit_colour(): void
    {
        $identity = Palette::IDENTITY;

        foreach (['red', '#abc', '#12345', 'rgb(0,0,0)', ''] as $bad) {
            $this->actingAs($this->admin())
                ->put('/admin/brand/palette', [...$identity, 'navy' => $bad])
                ->assertSessionHasErrors('navy');
        }

        $this->assertSame('#002546', $this->palette()->tokens()['--navy-900']);
    }

    #[Test]
    public function it_will_not_save_half_a_palette(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/brand/palette', ['navy' => '#1B3A2F'])
            ->assertSessionHasErrors(['lavender', 'orange', 'gold']);
    }

    #[Test]
    public function an_editor_without_the_settings_permission_cannot_change_the_identity(): void
    {
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('editor');

        $this->actingAs($editor)
            ->put('/admin/brand/palette', Palette::IDENTITY)
            ->assertForbidden();
    }

    #[Test]
    public function a_visitor_cannot_change_the_identity(): void
    {
        $this->put('/admin/brand/palette', Palette::IDENTITY)->assertRedirect();

        $this->assertSame('#002546', $this->palette()->tokens()['--navy-900']);
    }

    /**
     * The error pages ask for the colours and survive not getting them.
     *
     * They are deliberately the dumbest templates in the application, because
     * a 500 means something has already failed. Reading a settings row would
     * have put the database back on the path of the page that says the
     * database is down.
     */
    #[Test]
    public function the_error_pages_have_a_palette_even_with_no_settings_at_all(): void
    {
        Setting::query()->delete();
        app(Settings::class)->forget();

        $tokens = Palette::safely();

        $this->assertSame('#002546', $tokens['--navy-900']);
        $this->assertSame('#DCAD75', $tokens['--gold-400']);
        $this->assertSame('#9C4525', $tokens['--action-700']);
    }
}
