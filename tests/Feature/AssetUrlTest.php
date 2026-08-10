<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Uploaded-file URLs must not carry a hostname.
 *
 * Regression guard. Laravel's default `public` disk bakes APP_URL into every
 * URL it generates, so every image on the site broke the moment it was reached
 * on any other host — a dev port, staging, or the domain move in §16.
 *
 * Note for whoever edits this next: asserting "the URL does not contain
 * <some host>" is NOT a valid test here. The disk's url is resolved from
 * config at boot, so a Config::set('app.url', …) inside the test changes
 * nothing and the assertion passes whether or not the bug exists. Assert the
 * shape of the URL instead, which is what these tests do.
 */
class AssetUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StructureSeeder::class, NavigationSeeder::class]);

        Page::query()->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function sectorWithImage(): Sector
    {
        $sector = Sector::query()->where('key', Sector::KEY_GOVERNMENT)->sole();

        $sector->addMedia(UploadedFile::fake()->image('hero.jpg', 800, 600))
            ->toMediaCollection('hero');

        return $sector;
    }

    #[Test]
    public function the_public_disk_generates_root_relative_urls(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'));
    }

    #[Test]
    public function an_uploaded_image_url_is_root_relative(): void
    {
        $media = $this->sectorWithImage()->getFirstMedia('hero');

        $this->assertStringStartsWith('/storage/', $media->getUrl());
        $this->assertStringNotContainsString('://', $media->getUrl());
    }

    #[Test]
    public function a_rendered_page_serves_relative_image_sources(): void
    {
        $sector = $this->sectorWithImage();

        $response = $this->get("/ar/sectors/{$sector->slug}");
        $response->assertOk();

        preg_match_all('/src="([^"]*storage[^"]*)"/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'The page rendered no uploaded image at all.');

        foreach ($matches[1] as $src) {
            $this->assertStringStartsWith('/storage/', $src,
                "Image src [{$src}] carries a hostname; it will break on any other host.");
        }
    }

    #[Test]
    public function the_admin_panel_also_previews_images_by_a_relative_url(): void
    {
        // The panel had its own copy of the bug: ResourceController built
        // previews with getFullUrl(). It looked fine while APP_URL matched the
        // host in the address bar and broke everywhere else — the same failure
        // as the public site, in the one place a client would be least able to
        // describe it.
        $sector = $this->sectorWithImage();

        $this->seed(RolesSeeder::class);

        $admin = User::query()->create([
            'name' => 'Asset test',
            'email' => 'assets@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);
        $admin->assignRole(User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($admin)->get("/admin/content/sectors/{$sector->id}/edit");
        $response->assertOk();

        // Match from the opening quote, not from "storage" — a pattern that
        // starts at "storage" trims the hostname off before the assertion
        // sees it, and passes whether or not the bug is present. This one was
        // written the wrong way first and it passed against getFullUrl().
        preg_match_all('/src="([^"]*storage[^"]*)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches[1], 'The editor rendered no media preview at all.');

        foreach ($matches[1] as $src) {
            $this->assertStringStartsWith('/storage/', $src,
                "Admin preview [{$src}] carries a hostname; it will break on any other host.");
        }
    }

    #[Test]
    public function the_og_image_is_absolute_because_a_crawler_has_no_page_to_resolve_against(): void
    {
        $sector = $this->sectorWithImage();

        $response = $this->get("/ar/sectors/{$sector->slug}");

        $body = $response->getContent();

        preg_match('/property="og:image" content="([^"]*)"/', $body, $image);
        preg_match('/rel="canonical" href="([^"]*)"/', $body, $canonical);

        $this->assertNotEmpty($image[1] ?? null, 'No og:image was emitted.');
        $this->assertNotEmpty($canonical[1] ?? null, 'No canonical was emitted.');

        // The invariant that matters: og:image is absolute and shares an
        // origin with the canonical URL. Both are built from the request, so
        // they stay correct on whatever host actually serves the page.
        $origin = fn (string $url): string => parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);

        $this->assertSame($origin($canonical[1]), $origin($image[1]));
        $this->assertStringContainsString('/storage/', $image[1]);
    }
}
