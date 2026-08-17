<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Support\Locales;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Language negotiation at the root (§12).
 *
 * The rule, strongest signal first:
 *   1. what the visitor chose (cookie)
 *   2. what their browser asks for (Accept-Language, by q-value)
 *   3. Arabic
 *
 * Arabic wins a tie: a browser equally happy with both is telling us the
 * site's own default should decide.
 */
class LocaleNegotiationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    #[Test]
    #[DataProvider('browsers')]
    public function the_root_follows_the_browser_language(string $header, string $expected): void
    {
        $this->get('/', ['Accept-Language' => $header])
            ->assertRedirect("/{$expected}");
    }

    /** Headers real browsers and crawlers actually send. */
    public static function browsers(): array
    {
        return [
            'English (Chrome, US)' => ['en-US,en;q=0.9', 'en'],
            'English (Chrome, UK)' => ['en-GB,en;q=0.9', 'en'],
            'Arabic (Saudi)' => ['ar-SA,ar;q=0.9', 'ar'],
            'Arabic (Egypt)' => ['ar-EG,ar;q=0.9,en;q=0.8', 'ar'],
            'English first, Arabic second' => ['en-US,en;q=0.9,ar;q=0.8', 'en'],
            'Arabic first, English second' => ['ar,en;q=0.7', 'ar'],

            // Equal weight: the site's own default decides.
            'equal weight' => ['en;q=0.9,ar;q=0.9', 'ar'],

            // Neither language offered — fall back, never 404.
            'French only' => ['fr-FR,fr;q=0.9', 'ar'],
            'Chinese only' => ['zh-CN,zh;q=0.9', 'ar'],

            // A crawler or a bare client.
            'empty header' => ['', 'ar'],
            'wildcard only' => ['*', 'ar'],

            // q=0 means "I refuse this language", not "I accept it".
            'English explicitly refused' => ['en;q=0,fr;q=0.9', 'ar'],
            'Arabic refused, English fine' => ['ar;q=0,en;q=0.9', 'en'],
        ];
    }

    #[Test]
    public function the_root_sends_no_accept_language_at_all_to_arabic(): void
    {
        // Symfony's test client injects an English header by default, so this
        // has to be cleared explicitly to represent a header-less client.
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => ''])
            ->get('/')
            ->assertRedirect('/ar');
    }

    #[Test]
    public function a_stored_choice_beats_the_browser_language(): void
    {
        // The visitor picked Arabic; their browser still says English. What
        // they chose wins — re-guessing would override a deliberate decision.
        $this->withCookie(config('site.locale_cookie'), 'ar')
            ->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertRedirect('/ar');

        $this->withCookie(config('site.locale_cookie'), 'en')
            ->get('/', ['Accept-Language' => 'ar-SA,ar;q=0.9'])
            ->assertRedirect('/en');
    }

    #[Test]
    public function a_stale_cookie_for_a_language_no_longer_served_is_ignored(): void
    {
        $this->withCookie(config('site.locale_cookie'), 'fr')
            ->get('/', ['Accept-Language' => 'fr-FR'])
            ->assertRedirect('/ar');
    }

    #[Test]
    public function reading_a_page_remembers_that_language(): void
    {
        // Clicking the language switch is an ordinary link, so the preference
        // is captured from the page actually being read (§12).
        $this->get('/en')->assertCookie(config('site.locale_cookie'), 'en');
        $this->get('/ar')->assertCookie(config('site.locale_cookie'), 'ar');
    }

    #[Test]
    public function the_language_cookie_is_not_readable_by_scripts(): void
    {
        $cookie = collect($this->get('/ar')->headers->getCookies())
            ->firstWhere('getName', config('site.locale_cookie'))
            ?? collect($this->get('/ar')->headers->getCookies())
                ->first(fn ($c) => $c->getName() === config('site.locale_cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
    }

    #[Test]
    public function the_root_redirect_varies_by_language_and_cookie(): void
    {
        // A shared cache without this would pin one visitor's language for
        // everyone who follows.
        $response = $this->get('/', ['Accept-Language' => 'en-US']);

        $this->assertStringContainsString('Accept-Language', $response->headers->get('Vary'));
        $this->assertStringContainsString('Cookie', $response->headers->get('Vary'));
    }

    #[Test]
    public function english_is_never_offered_while_it_is_switched_off(): void
    {
        // §12/§20.5: management controls when English launches.
        Setting::query()->updateOrCreate(
            ['group' => 'site', 'key' => 'english_enabled'],
            ['value' => false, 'is_public' => true]
        );

        app(Settings::class)->forget();

        $this->assertSame(['ar'], Locales::enabled());

        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertRedirect('/ar');
    }

    #[Test]
    public function the_default_locale_is_listed_first_so_it_wins_ties(): void
    {
        $this->assertSame('ar', Locales::enabled()[0]);
    }
}
