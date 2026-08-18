<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every tracking field the panel offers must actually do something (§14.1).
 *
 * The tracking screen asked for six values, each with a placeholder and a
 * validation pattern, and only two of them were ever rendered: the Search
 * Console token and the GTM container. GA4, the Meta pixel and Clarity were
 * accepted, stored, echoed back on the screen — and dropped. An operator
 * pasting a GA4 id saw it save and got no analytics, with nothing anywhere
 * saying why.
 *
 * That is the failure this file exists to prevent, and it is the reason the
 * last test matters most: it walks the settings registry itself, so a seventh
 * field added later cannot quietly join the dead ones.
 */
class TrackingCodesReachThePageTest extends TestCase
{
    use RefreshDatabase;

    private function wrote(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'tracking', 'key' => $key],
            ['value' => $value],
        );

        // A query-builder write does not fire the events the settings cache
        // listens to.
        app(Settings::class)->forget();
    }

    #[Test]
    public function the_search_console_token_becomes_a_verification_tag(): void
    {
        $this->wrote('search_console', 'AbC123dEf456GhI789jKl012MnO345pQr678StU90');

        $this->get('/ar')
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="AbC123dEf456GhI789jKl012MnO345pQr678StU90">', false);
    }

    #[Test]
    public function the_tag_manager_container_is_loaded(): void
    {
        $this->wrote('gtm_id', 'GTM-ABC1234');

        $html = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('googletagmanager.com/gtm.js?id=GTM-ABC1234', $html);
        // The noscript iframe too — without it a visitor with JavaScript off
        // is invisible to every tag in the container.
        $this->assertStringContainsString('googletagmanager.com/ns.html?id=GTM-ABC1234', $html);
    }

    #[Test]
    public function a_ga4_measurement_id_is_configured(): void
    {
        $this->wrote('ga4_id', 'G-ABCDEFGH12');

        $html = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('gtag/js?id=G-ABCDEFGH12', $html);
        $this->assertStringContainsString("gtag('config', \"G-ABCDEFGH12\")", $html);
    }

    #[Test]
    public function a_meta_pixel_id_initialises_and_records_the_view(): void
    {
        $this->wrote('meta_pixel_id', '123456789012345');

        $html = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('connect.facebook.net', $html);
        $this->assertStringContainsString("fbq('init', \"123456789012345\")", $html);
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);
    }

    #[Test]
    public function a_clarity_id_loads_clarity(): void
    {
        $this->wrote('clarity_id', 'abcdefghij');

        $html = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('clarity.ms/tag/', $html);
        $this->assertStringContainsString('"abcdefghij"', $html);
    }

    /**
     * Nothing configured must mean nothing loaded. §15.1 budgets the page
     * weight, and a site with no analytics should fetch no analytics.
     */
    #[Test]
    public function an_empty_field_loads_nothing_and_raises_no_error(): void
    {
        $html = $this->get('/ar')->assertOk()->getContent();

        foreach (['googletagmanager.com', 'connect.facebook.net', 'clarity.ms'] as $vendor) {
            $this->assertStringNotContainsString($vendor, $html,
                "With no id configured the page still reached out to {$vendor}.");
        }
    }

    /**
     * The guard against the next dead field.
     *
     * Every key the tracking screen offers is filled in with a value that
     * satisfies its own pattern, and the page must then carry it somewhere.
     * meta_capi_token is the one exception and is named as such: Conversions
     * API is a server-to-server credential and has no business appearing in
     * the HTML — putting it there would publish a secret.
     */
    #[Test]
    public function no_field_on_the_tracking_screen_is_silently_ignored(): void
    {
        $samples = [
            'search_console' => 'AbC123dEf456GhI789jKl012MnO345pQr678StU90',
            'gtm_id' => 'GTM-ABC1234',
            'ga4_id' => 'G-ABCDEFGH12',
            'meta_pixel_id' => '123456789012345',
            'clarity_id' => 'abcdefghij',
        ];

        foreach ($samples as $key => $value) {
            $this->wrote($key, $value);
        }

        $html = $this->get('/ar')->assertOk()->getContent();

        foreach ($samples as $key => $value) {
            $this->assertStringContainsString($value, $html,
                "tracking.{$key} is offered by the panel, accepted, and never reaches the page. "
                .'A field that does nothing is worse than no field, because it is believed.');
        }
    }
}
