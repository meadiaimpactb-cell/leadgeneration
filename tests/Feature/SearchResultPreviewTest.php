<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Google-result preview in the page editor (§13).
 *
 * Its only value is being the same string the visitor is served. A preview
 * that drifts from the page teaches an editor to write for a picture that
 * does not exist — so what is asserted here is agreement with MetaBuilder,
 * not the presence of a box on a screen.
 */
class SearchResultPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        // The demo content is what publishes the pages. Without it every public
        // URL below answers 404 and the comparison this file exists for cannot
        // run at all.
        $this->seed([
            RolesSeeder::class,
            StructureSeeder::class,
            NavigationSeeder::class,
            LeadFieldsSeeder::class,
            DemoContentSeeder::class,
        ]);

        $this->admin = User::query()->create([
            'name' => 'Editor',
            'email' => 'preview@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);

        /*
         * The `about` page, not a page invented here: public pages are served
         * by literal routes, so a row with a new slug has no URL to compare
         * the preview against. See docs/seo-audit.md.
         */
        $this->page = Page::query()->where('slug', 'about')->sole();

        $this->page->translations()->updateOrCreate(['locale' => 'ar'], [
            'title' => 'الهدايا الحكومية',
            'meta_title' => 'هدايا مؤسسية حكومية',
            'meta_description' => 'قطع حرفية سعودية تليق بالمناسبات الرسمية.',
        ]);
    }

    /** @return array<string, mixed> */
    private function props(): array
    {
        return $this->actingAs($this->admin)
            ->get("/admin/pages/{$this->page->id}/edit")->assertOk()
            ->viewData('page')['props'];
    }

    #[Test]
    public function the_editor_is_given_what_it_needs_to_draw_the_result(): void
    {
        $props = $this->props();

        $this->assertSame(rtrim(url('/'), '/'), $props['baseUrl']);
        $this->assertArrayHasKey('ar', $props['siteNames']);
    }

    /**
     * The site name shown is the one the client set, not config('app.name') —
     * the preview and the served page must append the same words.
     */
    #[Test]
    public function the_site_name_is_the_one_the_panel_stores(): void
    {
        $expected = app(Settings::class)->get('site.name.ar');

        $this->assertSame($expected, $this->props()['siteNames']['ar']);
    }

    /**
     * The heart of it: the preview composes "meta title — site name", which is
     * exactly what MetaBuilder puts in <title>. This asserts the two agree on
     * a real page rather than trusting that they do.
     */
    #[Test]
    public function the_preview_composes_the_same_title_the_page_serves(): void
    {
        $props = $this->props();

        $composed = $props['page']['translations']['ar']['meta_title']
            .' — '.$props['siteNames']['ar'];

        $served = $this->get('/ar/about')->assertOk()
            ->viewData('page')['props']['seo']['fullTitle'];

        $this->assertSame($served, $composed,
            'The editor previews a title the visitor will never see.');
    }

    #[Test]
    public function the_preview_url_is_where_the_page_actually_lives(): void
    {
        $props = $this->props();

        $composed = $props['baseUrl'].'/ar/'.$props['page']['slug'];

        $this->assertSame($this->page->publicUrl('ar'), $composed);
        $this->get('/ar/about')->assertOk();
    }

    /** A page hidden from search engines must say so, whatever is typed. */
    #[Test]
    public function the_indexable_flag_reaches_the_editor(): void
    {
        $this->assertTrue($this->props()['page']['is_indexable']);

        $this->page->forceFill(['is_indexable' => false])->save();

        $this->assertFalse($this->props()['page']['is_indexable']);
    }

    #[Test]
    public function a_visitor_cannot_reach_the_editor(): void
    {
        $this->get("/admin/pages/{$this->page->id}/edit")->assertRedirect('/admin/login');
    }
}
