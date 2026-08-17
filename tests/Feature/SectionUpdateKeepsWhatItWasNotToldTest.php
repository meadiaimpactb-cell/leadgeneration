<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Updating one part of a section must not erase the parts nobody mentioned.
 *
 * The endpoint used to read `$data['settings'] ?? null`, so a request that
 * did not mention `settings` overwrote it with null — taking the section's
 * cards, questions and gallery images with it — and `is_active ?? true`
 * silently switched a disabled section back on.
 *
 * The panel's builder always posts every field, so this was invisible from
 * the interface. It destroyed the content of 77 sections the first time
 * anything else PATCHed the endpoint, and reported success while doing it:
 * the components simply render nothing once `items` is gone, so the page went
 * quiet rather than broken.
 *
 * These tests are the ones that would have caught it.
 */
class SectionUpdateKeepsWhatItWasNotToldTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');

        $page = Page::query()->firstOrFail();

        $this->section = $page->sections()->create([
            'type' => 'cards',
            'sort_order' => 99,
            'is_active' => true,
            'settings' => ['items' => [['title' => 'one'], ['title' => 'two']], 'eyebrow' => 'KEEP ME'],
        ]);

        $this->section->translations()->create(['locale' => 'ar', 'heading' => 'العنوان الأصلي']);
    }

    #[Test]
    public function editing_only_the_heading_leaves_the_settings_untouched(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/sections/{$this->section->id}", [
                'translations' => ['ar' => ['heading' => 'عنوان جديد']],
            ])
            ->assertRedirect();

        $this->section->refresh();

        $this->assertCount(2, $this->section->settings['items'] ?? []);
        $this->assertSame('KEEP ME', $this->section->settings['eyebrow'] ?? null);
        $this->assertSame('عنوان جديد', $this->section->translations()->where('locale', 'ar')->value('heading'));
    }

    /**
     * The other half of the same bug: a section the client switched OFF must
     * not switch itself back on because an unrelated edit did not mention it.
     */
    #[Test]
    public function editing_a_disabled_section_does_not_re_enable_it(): void
    {
        $this->section->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->patch("/admin/sections/{$this->section->id}", [
                'translations' => ['ar' => ['heading' => 'عنوان جديد']],
            ])
            ->assertRedirect();

        $this->assertFalse($this->section->refresh()->is_active);
    }

    /** Clearing settings on purpose still has to work. */
    #[Test]
    public function sending_settings_explicitly_still_replaces_them(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/sections/{$this->section->id}", [
                'settings' => ['items' => [['title' => 'only one']]],
            ])
            ->assertRedirect();

        $this->assertCount(1, $this->section->refresh()->settings['items'] ?? []);
    }

    #[Test]
    public function sending_a_null_settings_value_clears_them(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/sections/{$this->section->id}", ['settings' => null])
            ->assertRedirect();

        $this->assertEmpty($this->section->refresh()->settings);
    }
}
