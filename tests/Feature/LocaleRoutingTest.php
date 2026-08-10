<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locale routing and the SEO guarantees that depend on it (§12, §13).
 */
class LocaleRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([StructureSeeder::class, NavigationSeeder::class]);
    }

    #[Test]
    public function the_root_falls_back_to_the_default_locale(): void
    {
        // A language the site does not serve must land on Arabic, not 404
        // and not English. (Symfony's test client injects an English
        // Accept-Language by default, so this header has to be explicit.)
        $this->get('/', ['Accept-Language' => 'fr-FR,fr;q=0.9'])
            ->assertRedirect('/ar');
    }

    #[Test]
    public function the_root_honours_a_stored_preference_over_the_browser(): void
    {
        // §12: the visitor's own choice is remembered in a cookie and wins.
        $this->withCookie(config('site.locale_cookie'), 'ar')
            ->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertRedirect('/ar');
    }

    #[Test]
    public function the_root_honours_accept_language(): void
    {
        $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9,ar;q=0.5'])
            ->assertRedirect('/en');
    }

    #[Test]
    public function the_root_prefers_arabic_when_it_is_requested_more_strongly(): void
    {
        $this->get('/', ['Accept-Language' => 'ar,en;q=0.4'])
            ->assertRedirect('/ar');
    }

    #[Test]
    public function the_root_redirect_is_temporary_not_permanent(): void
    {
        // A 301 here would be cached by the browser and would pin the visitor
        // to whichever locale they saw first (§12).
        $this->get('/')->assertStatus(302);
    }

    #[Test]
    public function both_locales_serve_the_home_page(): void
    {
        $this->get('/ar')->assertOk();
        $this->get('/en')->assertOk();
    }

    #[Test]
    public function an_unknown_locale_is_a_404_not_a_fallback(): void
    {
        $this->get('/fr')->assertNotFound();
    }

    #[Test]
    public function the_arabic_page_is_rtl_and_the_english_page_is_ltr(): void
    {
        $this->get('/ar')->assertSee('<html lang="ar" dir="rtl">', false);
        $this->get('/en')->assertSee('<html lang="en" dir="ltr">', false);
    }

    #[Test]
    public function it_advertises_reciprocal_hreflang_with_x_default(): void
    {
        $response = $this->get('/ar');

        $response->assertSee('hreflang="ar"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
    }
}
