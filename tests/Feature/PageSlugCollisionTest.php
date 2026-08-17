<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A duplicate URL identifier is a field error, not a stack trace.
 *
 * `pages.slug` is UNIQUE and the model soft-deletes, so a deleted page keeps
 * its slug reserved — invisibly, since nothing in the panel lists the bin. The
 * form validated the slug for length and nothing else, so the collision
 * surfaced as an unhandled UniqueConstraintViolationException: a 500 and a
 * white screen for a typo. Observed live by the client while adding a page.
 */
class PageSlugCollisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, StructureSeeder::class]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super-admin');
    }

    /** @return array<string, mixed> */
    private function payload(string $slug): array
    {
        return [
            'slug' => $slug,
            'template' => 'default',
            'is_indexable' => true,
            'translations' => [
                'ar' => ['title' => 'عنوان'],
                'en' => ['title' => 'Title'],
            ],
        ];
    }

    #[Test]
    public function a_slug_already_taken_by_a_live_page_is_refused_on_the_field(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/pages', $this->payload('about'))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Page::query()->where('slug', 'about')->count());
    }

    #[Test]
    public function deleting_a_page_gives_its_name_back(): void
    {
        /*
         * The client's own words: "if I added a page and deleted it, the
         * system should know it is deleted and let me add the same name again
         * with no trouble." It could not. The UNIQUE index counts soft-deleted
         * rows and the panel shows no bin, so the name was held by something
         * invisible and unreleasable — and adding it again was a 500.
         */
        $deleted = Page::query()->create([
            'slug' => 'services',
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $deleted->delete();

        $this->actingAs($this->admin)
            ->post('/admin/pages', $this->payload('services'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'services', 'deleted_at' => null]);
    }

    #[Test]
    public function the_deleted_page_is_kept_and_stays_recoverable(): void
    {
        // Releasing the name must not cost the row. The deletion stays
        // reversible and the activity log stays honest about what existed.
        $deleted = Page::query()->create(['slug' => 'services', 'template' => 'default']);
        $id = $deleted->id;
        $deleted->delete();

        $this->assertSoftDeleted('pages', ['id' => $id]);

        $parked = Page::withTrashed()->findOrFail($id);

        $this->assertNotSame('services', $parked->slug);
        $this->assertStringStartsWith('services', $parked->slug);
    }

    #[Test]
    public function a_restored_page_takes_its_name_back_when_it_is_still_free(): void
    {
        $page = Page::query()->create(['slug' => 'services', 'template' => 'default']);
        $page->delete();
        $page->restore();

        $this->assertSame('services', $page->fresh()->slug);
    }

    #[Test]
    public function a_restored_page_does_not_snatch_a_name_someone_else_has_taken(): void
    {
        // A live page under the name outranks a row coming back from the bin;
        // otherwise restoring would break the URL of a page being served.
        $old = Page::query()->create(['slug' => 'services', 'template' => 'default']);
        $old->delete();

        $new = Page::query()->create(['slug' => 'services', 'template' => 'default']);

        $old->restore();

        $this->assertNotSame('services', $old->fresh()->slug);
        $this->assertSame('services', $new->fresh()->slug);
    }

    #[Test]
    public function the_slug_is_normalised_before_it_is_checked_not_after(): void
    {
        /*
         * The hole a plain `unique` rule leaves. The stored value is
         * `Str::slug()` of the input, so "About" is unique against a table
         * holding "about" — it passes validation and collides on insert, which
         * is the same 500 by a longer road.
         */
        $this->actingAs($this->admin)
            ->post('/admin/pages', $this->payload('About'))
            ->assertSessionHasErrors('slug');
    }

    #[Test]
    public function filling_a_language_without_naming_it_is_refused_rather_than_discarded(): void
    {
        /*
         * The silent loss. A blank column means "not translated" and deletes
         * the row — deliberate, §12. But an editor who filled in a subtitle and
         * left the title empty got a success message while everything they had
         * typed was deleted. Saying nothing still means absent; saying
         * something now means the page needs a name.
         */
        $payload = $this->payload('advisory');
        $payload['translations']['ar'] = ['title' => '', 'subtitle' => 'عنوان فرعي بلا عنوان'];

        $this->actingAs($this->admin)
            ->post('/admin/pages', $payload)
            ->assertSessionHasErrors('translations.ar.title');
    }

    #[Test]
    public function a_language_left_entirely_blank_is_still_simply_absent(): void
    {
        // The other half: the guard must not turn "this page has no English"
        // into an error, which is a perfectly ordinary state (§12).
        $payload = $this->payload('advisory');
        $payload['translations']['en'] = ['title' => '', 'subtitle' => ''];

        $this->actingAs($this->admin)
            ->post('/admin/pages', $payload)
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_free_slug_still_saves(): void
    {
        // The rule must refuse collisions without refusing the normal case.
        $this->actingAs($this->admin)
            ->post('/admin/pages', $this->payload('our-services'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'our-services']);
    }

    #[Test]
    public function a_page_may_be_saved_again_without_colliding_with_itself(): void
    {
        // `ignore()` earns its place here: without it, editing any page and
        // pressing save fails against the page's own row.
        $page = Page::query()->create(['slug' => 'our-services', 'template' => 'default']);

        $this->actingAs($this->admin)
            ->patch("/admin/pages/{$page->id}", $this->payload('our-services'))
            ->assertSessionHasNoErrors();
    }
}
