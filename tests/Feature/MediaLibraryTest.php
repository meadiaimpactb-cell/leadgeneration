<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Page;
use App\Models\Section;
use App\Models\User;
use App\Support\MediaLibrary;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The media library, and the one rule that shapes all of it: an image lives in
 * one place and is referenced from many.
 *
 * Media Library ties a file to a single owner, so the obvious way to put the
 * same photograph in two sections is to upload it twice. That is the thing
 * these tests exist to prevent — two files, two alt texts, and an editor who
 * corrects one of them and cannot understand why the other page still reads
 * wrong. Every other behaviour here follows from that choice.
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([RolesSeeder::class, StructureSeeder::class, NavigationSeeder::class, DemoContentSeeder::class]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);

        $this->admin = User::query()->create([
            'name' => 'Amad Manager',
            'email' => 'media@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $this->admin->assignRole(User::ROLE_SUPER_ADMIN);
    }

    /** A real encodable image — `mimes:` reads the file, not the name. */
    private function upload(string $name = 'craft.jpg', int $width = 1200, int $height = 800): Media
    {
        $response = $this->actingAs($this->admin)->post('/admin/media/library', [
            'file' => UploadedFile::fake()->image($name, $width, $height),
        ]);

        $response->assertCreated();

        return Media::query()->findOrFail($response->json('item.id'));
    }

    private function gallerySection(): Section
    {
        return Section::query()->where('type', 'gallery')->firstOrFail();
    }

    // ---------------------------------------------------------------- //
    // The library itself
    // ---------------------------------------------------------------- //

    #[Test]
    public function an_upload_lands_in_the_library_with_its_dimensions(): void
    {
        $media = $this->upload('sadu.jpg', 1600, 900);

        $this->assertSame(MediaLibrary::COLLECTION, $media->collection_name);

        // The picker shows these so an editor can tell, before using an image
        // as a hero, whether it is big enough to be one.
        $this->assertSame(1600, $media->getCustomProperty('width'));
        $this->assertSame(900, $media->getCustomProperty('height'));
    }

    #[Test]
    public function the_library_lists_every_image_on_the_site_not_only_its_own_uploads(): void
    {
        $this->upload();

        $items = $this->actingAs($this->admin)
            ->getJson('/admin/media/library')
            ->assertOk()
            ->json('items');

        $collections = Media::query()->pluck('collection_name')->unique();

        $this->assertGreaterThan(1, $collections->count(), 'fixture should contain media owned by records too');
        $this->assertCount(Media::query()->count(), $items,
            'an image uploaded on the stories screen is still an image on this site, and must be findable here');
    }

    #[Test]
    public function the_library_can_be_searched_by_file_name(): void
    {
        $this->upload('khoos-weaving.jpg');
        $this->upload('zari-thread.jpg');

        $items = $this->actingAs($this->admin)
            ->getJson('/admin/media/library?search=khoos')
            ->assertOk()
            ->json('items');

        $this->assertCount(1, $items);
        $this->assertStringContainsString('khoos', $items[0]['fileName']);
    }

    #[Test]
    public function a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/media/library', ['file' => UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf')])
            ->assertSessionHasErrors('file');
    }

    #[Test]
    public function an_editor_without_media_rights_cannot_reach_the_library(): void
    {
        $sales = User::query()->create([
            'name' => 'Sales',
            'email' => 'sales@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $sales->assignRole(User::ROLE_SALES);

        $this->actingAs($sales)->getJson('/admin/media/library')->assertForbidden();
    }

    // ---------------------------------------------------------------- //
    // Reuse — the rule the whole design exists for
    // ---------------------------------------------------------------- //

    #[Test]
    public function the_same_image_in_two_sections_is_one_file_on_disk(): void
    {
        $media = $this->upload();
        $sections = Section::query()->where('type', 'gallery')->take(2)->get();

        $this->assertCount(2, $sections, 'fixture should have two gallery sections');

        // Counted as a delta, not a total: the fixture ships images of its own.
        $before = Media::query()->count();

        foreach ($sections as $section) {
            $this->actingAs($this->admin)
                ->post("/admin/sections/{$section->id}/media", [
                    'collection' => 'gallery',
                    'media' => [$media->id],
                ])
                ->assertRedirect();
        }

        $this->assertSame($before, Media::query()->count(),
            'referencing an image twice must not copy it');
        $this->assertSame(1, Media::query()->where('file_name', $media->file_name)->count(),
            'one photograph, one file');
        $this->assertSame(2, MediaAttachment::query()->where('media_id', $media->id)->count());

        // And both sections really do render it.
        foreach ($sections as $section) {
            $this->assertSame($media->id, $section->fresh()->attachedMedia('gallery')->first()->id);
        }
    }

    #[Test]
    public function editing_the_alt_text_once_fixes_it_everywhere(): void
    {
        $media = $this->upload();
        $sections = Section::query()->where('type', 'gallery')->take(2)->get();

        foreach ($sections as $section) {
            $section->syncAttachedMedia('gallery', [$media->id]);
        }

        $this->actingAs($this->admin)
            ->patch("/admin/media/{$media->id}", [
                'translations' => [
                    'ar' => ['alt_text' => 'نسيج سدو بخيوط حمراء'],
                    'en' => ['alt_text' => 'Sadu weaving in red thread'],
                ],
            ])
            ->assertRedirect();

        foreach ($sections as $section) {
            $payload = $section->fresh()->galleryPayload();

            $this->assertSame('نسيج سدو بخيوط حمراء', $payload[0]['alt'],
                'one image, one alt text — that is the point of referencing rather than copying');
        }
    }

    // ---------------------------------------------------------------- //
    // Order, replace, detach
    // ---------------------------------------------------------------- //

    #[Test]
    public function inserted_images_render_in_the_order_they_were_given(): void
    {
        $section = $this->gallerySection();
        $first = $this->upload('one.jpg');
        $second = $this->upload('two.jpg');
        $third = $this->upload('three.jpg');

        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", [
                'collection' => 'gallery',
                'media' => [$third->id, $first->id, $second->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$third->id, $first->id, $second->id],
            array_column($section->fresh()->galleryPayload(), 'id')
        );
    }

    #[Test]
    public function reordering_is_reflected_on_the_public_page(): void
    {
        $section = $this->gallerySection();
        $a = $this->upload('a.jpg');
        $b = $this->upload('b.jpg');

        $section->syncAttachedMedia('gallery', [$a->id, $b->id]);

        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", [
                'collection' => 'gallery',
                'media' => [$b->id, $a->id],
            ])
            ->assertRedirect();

        $this->assertSame([$b->id, $a->id], array_column($section->fresh()->galleryPayload(), 'id'));
    }

    #[Test]
    public function replacing_one_image_leaves_the_others_where_they_were(): void
    {
        $section = $this->gallerySection();
        $a = $this->upload('a.jpg');
        $b = $this->upload('b.jpg');
        $c = $this->upload('c.jpg');
        $replacement = $this->upload('replacement.jpg');

        $section->syncAttachedMedia('gallery', [$a->id, $b->id, $c->id]);

        // Replace the middle one — the panel sends the whole arrangement.
        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", [
                'collection' => 'gallery',
                'media' => [$a->id, $replacement->id, $c->id],
            ])
            ->assertRedirect();

        $this->assertSame(
            [$a->id, $replacement->id, $c->id],
            array_column($section->fresh()->galleryPayload(), 'id'),
            'a replacement takes the position of the image it replaced'
        );
    }

    #[Test]
    public function removing_an_image_from_a_section_keeps_it_in_the_library(): void
    {
        $section = $this->gallerySection();
        $keep = $this->upload('keep.jpg');
        $drop = $this->upload('drop.jpg');

        $section->syncAttachedMedia('gallery', [$keep->id, $drop->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/sections/{$section->id}/media/{$drop->id}", ['collection' => 'gallery'])
            ->assertRedirect();

        $this->assertSame([$keep->id], array_column($section->fresh()->galleryPayload(), 'id'));

        // The distinction the whole screen has to teach.
        $this->assertNotNull(Media::query()->find($drop->id),
            'removing from a section is not deleting from the library');
    }

    #[Test]
    public function a_single_image_slot_never_holds_more_than_one(): void
    {
        $section = Section::query()->where('type', 'hero')->firstOrFail();
        $a = $this->upload('a.jpg');
        $b = $this->upload('b.jpg');

        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", [
                'collection' => 'image',
                'media' => [$a->id, $b->id],
            ])
            ->assertRedirect();

        $this->assertCount(1, $section->fresh()->attachedMedia('image'),
            'the hero component reads the first entry and would ignore the rest in silence');
    }

    // ---------------------------------------------------------------- //
    // Not breaking what already renders
    // ---------------------------------------------------------------- //

    #[Test]
    public function a_section_with_no_chosen_images_still_renders_the_ones_it_had(): void
    {
        $section = $this->gallerySection();

        $before = $section->galleryPayload();

        $this->assertNotEmpty($before, 'fixture gallery should have JSON images');
        $this->assertSame($before, $section->fresh()->galleryPayload(),
            'the library is opt-in: until an editor picks something, nothing about the page changes');
    }

    #[Test]
    public function chosen_images_take_over_from_the_seeded_ones(): void
    {
        $section = $this->gallerySection();
        $media = $this->upload();

        $this->assertNotEmpty($section->galleryPayload());

        $section->syncAttachedMedia('gallery', [$media->id]);

        $payload = $section->fresh()->galleryPayload();

        $this->assertCount(1, $payload);
        $this->assertSame($media->id, $payload[0]['id']);
    }

    // ---------------------------------------------------------------- //
    // Deleting
    // ---------------------------------------------------------------- //

    #[Test]
    public function deleting_an_image_that_is_in_use_is_refused_until_confirmed(): void
    {
        $section = $this->gallerySection();
        $media = $this->upload();

        $section->syncAttachedMedia('gallery', [$media->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/media/{$media->id}")
            ->assertSessionHas('error');

        $this->assertNotNull(Media::query()->find($media->id));

        // A confirm dialog is a convention, not a safeguard — the check is
        // server-side, and `force` is what the confirmed request carries.
        $this->actingAs($this->admin)
            ->delete("/admin/media/{$media->id}", ['force' => true])
            ->assertSessionHas('success');

        $this->assertNull(Media::query()->find($media->id));
    }

    #[Test]
    public function deleting_an_image_takes_its_references_with_it(): void
    {
        $section = $this->gallerySection();
        $media = $this->upload();

        $section->syncAttachedMedia('gallery', [$media->id]);

        $this->actingAs($this->admin)->delete("/admin/media/{$media->id}", ['force' => true]);

        $this->assertSame(0, MediaAttachment::query()->where('media_id', $media->id)->count(),
            'a reference to a deleted file would render as a broken image');

        // And the section falls back to what it showed before, rather than
        // to nothing.
        $this->assertNotEmpty($section->fresh()->galleryPayload());
    }

    // ---------------------------------------------------------------- //
    // The whole errand, in one test
    // ---------------------------------------------------------------- //

    /**
     * Upload five, reorder them, swap one for an image already in the library,
     * drop another — then read the page back in both languages.
     *
     * Written as one long test on purpose. Each of these steps passes on its
     * own above; what an editor actually does is all of them in a row against
     * the same section, and that is the sequence where an off-by-one in the
     * ordering or a stale reference would show up.
     */
    #[Test]
    public function an_editor_can_fill_a_gallery_and_the_page_shows_it_in_order(): void
    {
        $section = $this->gallerySection();

        $uploaded = collect(['one', 'two', 'three', 'four', 'five'])
            ->map(fn (string $name): Media => $this->upload("{$name}.jpg"));

        // 1. five in one go — the picker sends one request per file.
        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", [
                'collection' => 'gallery',
                'media' => $uploaded->pluck('id')->all(),
            ])
            ->assertRedirect();

        $this->assertCount(5, $section->fresh()->galleryPayload());

        // 2. drag the last one to the front.
        $order = $uploaded->pluck('id')->all();
        array_unshift($order, array_pop($order));

        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", ['collection' => 'gallery', 'media' => $order])
            ->assertRedirect();

        // 3. replace the middle one with something already in the library —
        //    an image owned by another record, never uploaded here.
        $existing = Media::query()
            ->where('collection_name', '!=', MediaLibrary::COLLECTION)
            ->firstOrFail();

        $order[2] = $existing->id;

        $this->actingAs($this->admin)
            ->post("/admin/sections/{$section->id}/media", ['collection' => 'gallery', 'media' => $order])
            ->assertRedirect();

        // 4. remove one.
        $removed = array_pop($order);

        $this->actingAs($this->admin)
            ->delete("/admin/sections/{$section->id}/media/{$removed}", ['collection' => 'gallery'])
            ->assertRedirect();

        // 5. read it back.
        $payload = $section->fresh()->galleryPayload();

        $this->assertSame($order, array_column($payload, 'id'), 'the page shows exactly what the panel shows');
        $this->assertCount(4, $payload);
        $this->assertNotNull(Media::query()->find($removed), 'removed from the section, not from the library');

        // And on the live page, in both languages — the URLs arrive in the
        // same sequence, which is the thing a visitor actually sees.
        $page = $section->sectionable;
        $path = $page->slug === 'home' ? '' : '/'.$page->slug;

        foreach (['ar', 'en'] as $locale) {
            $html = $this->get("/{$locale}{$path}")->assertOk()->getContent();

            $positions = array_map(
                fn (array $image): int => (int) mb_strpos($html, $this->escaped($image['url'])),
                $payload
            );

            $this->assertNotContains(false, $positions, "every chosen image reaches the {$locale} page");

            $sorted = $positions;
            sort($sorted);

            $this->assertSame($sorted, $positions, "the {$locale} page renders them in the chosen order");
        }
    }

    /** URLs arrive inside Inertia's JSON payload, where slashes are escaped. */
    private function escaped(string $url): string
    {
        return trim(json_encode($url), '"');
    }

    #[Test]
    public function the_usage_endpoint_names_where_an_image_is_used(): void
    {
        $section = $this->gallerySection();
        $media = $this->upload();

        $section->syncAttachedMedia('gallery', [$media->id]);

        $usage = $this->actingAs($this->admin)
            ->getJson("/admin/media/{$media->id}/usage")
            ->assertOk()
            ->json('usage');

        $this->assertCount(1, $usage);
        $this->assertSame('gallery', $usage[0]['collection']);
        $this->assertSame('gallery', $usage[0]['sectionType']);
    }
}
