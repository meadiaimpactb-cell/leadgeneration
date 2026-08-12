<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeadField;
use App\Models\Navigation;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Setting;
use Database\Seeders\Concerns\SeedsRows;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RedirectsSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The seeders' two-way promise, made permanent.
 *
 * A structural flag is repaired on every run; a column the client owns is
 * never touched again after creation. Both halves matter, and each one is the
 * other's bug: a seeder that enforces nothing lets a wrong flag survive
 * forever, and a seeder that enforces everything walks over the client's work.
 *
 * The first half already cost us something real. `contact.header_cta.*` was
 * created by the wrong seeder with `is_public` at its column default,
 * StructureSeeder's next run found the rows present and changed nothing, and
 * the «لنبدأ معًا» button vanished from every page with nothing in any log to
 * say why. These tests break loudly if that door reopens.
 *
 * @see SeedsRows
 */
class SeederFlagsSurviveARerunTest extends TestCase
{
    use RefreshDatabase;

    private function seedStructure(): void
    {
        $this->seed([
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
            RedirectsSeeder::class,
        ]);
    }

    // ---------------------------------------------------------------- //
    // Repaired: structure the panel does not expose
    // ---------------------------------------------------------------- //

    /** The exact failure that lost the header button. */
    #[Test]
    public function a_setting_that_lost_its_is_public_flag_is_repaired(): void
    {
        $this->seedStructure();

        Setting::query()
            ->where('group', 'contact')
            ->where('key', 'header_cta.ar')
            ->update(['is_public' => false]);

        $this->seedStructure();

        $this->assertTrue(
            Setting::query()->where('group', 'contact')->where('key', 'header_cta.ar')->value('is_public'),
            'is_public is structure and must be re-asserted on every run.'
        );
    }

    /**
     * NavigationBuilder skips an inactive menu, and nothing in the panel can
     * switch one back on — so an inactive row is not a decision, it is damage.
     */
    #[Test]
    public function a_menu_switched_off_outside_the_panel_is_switched_back_on(): void
    {
        $this->seedStructure();

        Navigation::query()->where('key', Navigation::HEADER)->update(['is_active' => false]);

        $this->seedStructure();

        $this->assertTrue(Navigation::query()->where('key', Navigation::HEADER)->value('is_active'));
    }

    /**
     * `is_locked` is what keeps the contact field un-switchable-off, and
     * LeadFieldController forces a locked field on whatever the request says.
     * So the lock and the two flags it governs are all repaired together —
     * a locked-but-disabled row would be a form with nowhere to reply to.
     */
    #[Test]
    public function the_locked_contact_field_is_fully_restored(): void
    {
        $this->seedStructure();

        LeadField::query()->where('key', LeadField::KEY_CONTACT)->update([
            'is_locked' => false,
            'is_enabled' => false,
            'is_required' => false,
        ]);

        $this->seedStructure();

        $field = LeadField::query()->where('key', LeadField::KEY_CONTACT)->first();

        $this->assertTrue($field->is_locked, 'the lock itself must come back');
        $this->assertTrue($field->is_enabled, 'a locked field is always on');
        $this->assertTrue($field->is_required, 'a locked field is always required');
    }

    // ---------------------------------------------------------------- //
    // Left alone: everything the panel does expose
    // ---------------------------------------------------------------- //

    /** Content is the client's. A re-seed is not an editorial review. */
    #[Test]
    public function an_edited_setting_value_is_never_rewritten(): void
    {
        $this->seedStructure();

        Setting::query()
            ->where('group', 'contact')
            ->where('key', 'header_cta.ar')
            ->update(['value' => json_encode('لنبدأ معًا')]);

        $this->seedStructure();

        $this->assertSame(
            'لنبدأ معًا',
            Setting::query()->where('group', 'contact')->where('key', 'header_cta.ar')->value('value')
        );
    }

    /** Publishing is a button in the panel, not a column this file owns. */
    #[Test]
    public function a_published_page_is_not_dragged_back_to_draft(): void
    {
        $this->seedStructure();

        Page::query()->where('slug', 'about')->update(['status' => 'published', 'sort_order' => 99]);

        $this->seedStructure();

        $page = Page::query()->where('slug', 'about')->first();

        $this->assertSame('published', $page->status);
        $this->assertSame(99, $page->sort_order, 'a reorder made in the panel must survive');
    }

    /**
     * An extra field exists precisely so Amad Craft can switch it on. A
     * re-seed that switched it back off would make the panel a suggestion.
     */
    #[Test]
    public function an_unlocked_field_the_client_enabled_stays_enabled(): void
    {
        $this->seedStructure();

        LeadField::query()->where('key', 'name')->update(['is_enabled' => true]);

        $this->seedStructure();

        $this->assertTrue(LeadField::query()->where('key', 'name')->value('is_enabled'));
    }

    /**
     * A redirect the client re-pointed is a live SEO decision, and re-imposing
     * this file's target on every run would undo it silently (§22.7).
     */
    #[Test]
    public function a_redirect_the_client_repointed_is_left_where_they_put_it(): void
    {
        $this->seedStructure();

        $from = '/ar/sectors/artisans';

        $this->assertTrue(Redirect::query()->where('from_path', $from)->exists(), 'fixture path changed');

        Redirect::query()->where('from_path', $from)->update([
            'to_path' => '/ar/contact',
            'is_active' => false,
        ]);

        $this->seedStructure();

        $redirect = Redirect::query()->where('from_path', $from)->first();

        $this->assertSame('/ar/contact', $redirect->to_path);
        $this->assertFalse($redirect->is_active);
    }
}
