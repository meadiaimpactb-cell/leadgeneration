<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\LeadField;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The always-on-screen contact dock, and the modal it opens.
 *
 * The dock was removed from the standard public layout at the client's
 * request. It survives on campaign pages only: a landing page has no header
 * navigation and no footer contact block, so the dock is its one
 * always-reachable route to the field, which is the page's single goal
 * (§11.3).
 *
 * The risk it always carried is turning one form into two, which §6.1 forbids
 * — so these tests care less about the dock looking right than about it
 * staying a *button that opens the one form*.
 */
class ContactDockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    private function campaignPath(): string
    {
        $campaign = Campaign::query()->where('is_active', true)->first();

        if ($campaign === null) {
            $this->markTestSkipped('No campaign seeded.');
        }

        return "/ar/c/{$campaign->slug}";
    }

    #[Test]
    public function the_dock_is_rendered_server_side_on_a_campaign_page(): void
    {
        // It used to be wrapped in <Teleport to="body">, which Inertia's SSR
        // pass does not emit — so the whole CTA appeared only after hydration.
        $this->get($this->campaignPath())
            ->assertOk()
            ->assertSee('class="dock"', false);
    }

    #[Test]
    public function the_standard_public_layout_no_longer_carries_the_dock(): void
    {
        foreach (['/ar', '/en', '/ar/products', '/ar/about', '/ar/impact'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('class="dock"', false);
        }
    }

    #[Test]
    public function opening_it_still_submits_to_the_one_lead_endpoint(): void
    {
        $body = $this->get('/ar')->assertOk()->getContent();

        // A page legitimately renders the field twice — once in its CTA band
        // and once inside the (closed) modal. That is not two forms; it is one
        // component rendered twice. The invariant that matters is that every
        // contact input on the page IS that component: same name, same
        // autocomplete pair, so all of them hit the one endpoint with the one
        // payload the server validates.
        $canonical = substr_count($body, 'autocomplete="email tel"');

        $this->assertGreaterThan(0, $canonical);
        $this->assertSame(
            $canonical,
            substr_count($body, 'name="contact"'),
            'A contact input was rendered that is not the shared LeadField — §6.1 allows one form.',
        );

        // And nothing on the page posts anywhere but /leads.
        preg_match_all('/<form[^>]*action="([^"]*)"/', $body, $actions);
        foreach ($actions[1] as $action) {
            $this->assertStringContainsString('/leads', $action);
        }
    }

    #[Test]
    public function it_never_asks_for_a_persons_name(): void
    {
        // Asserted against the field configuration, not the markup.
        //
        // The markup assertion this replaces looked for `name="name"` in the
        // HTML — but LeadField renders extra inputs with an id and no name
        // attribute, so the string could never appear and the test passed
        // whether or not a personal-name field was switched on. It was
        // guarding nothing.
        //
        // §6.1 forbids asking for a person's name. It does not forbid asking
        // for a company, which the approved design does ask for and which the
        // demo seeder enables — so this checks the one field that must stay
        // off, and says why the other may be on.
        $this->assertFalse(
            LeadField::query()->where('key', 'name')->value('is_enabled'),
            'The personal-name field must stay disabled — §6.1.',
        );

        $body = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringNotContainsString('autocomplete="name"', $body);
        $this->assertStringNotContainsString('autocomplete="given-name"', $body);
    }

    /*
     * Two tests were removed here, deliberately rather than by neglect: they
     * covered the dock rendering tel: and WhatsApp buttons, and the same
     * buttons vanishing when those settings are empty. The only page that
     * still mounts the dock passes `channels: false`, so neither state is
     * reachable over HTTP any more and a test asserting them would have been
     * testing a code path nothing can enter. The direct channels still reach
     * the visitor — through the footer, which FooterTest covers.
     */

    #[Test]
    public function a_campaign_page_gets_the_field_but_no_way_off_the_page(): void
    {
        $campaign = Campaign::query()->where('is_active', true)->first();

        if ($campaign === null) {
            $this->markTestSkipped('No campaign seeded.');
        }

        $body = $this->get("/ar/c/{$campaign->slug}")->assertOk()->getContent();

        // §11.3: no outbound links on a campaign page except legal. A tel: or
        // WhatsApp button is a way off the page, so the dock drops them there
        // and keeps only the lead button.
        $this->assertStringContainsString('class="dock"', $body);
        $this->assertStringNotContainsString('wa.me', $body);
        $this->assertStringNotContainsString('href="tel:', $body);
    }

    #[Test]
    public function the_client_can_switch_the_whole_dock_off(): void
    {
        Setting::query()->where('group', 'site')->where('key', 'contact_dock')
            ->update(['value' => json_encode(false)]);

        $this->get($this->campaignPath())->assertOk()->assertDontSee('class="dock"', false);
    }

    #[Test]
    public function the_dock_carries_no_copy_of_its_own(): void
    {
        // Its heading and note are content and live in settings (§0.1). With
        // them cleared the dock still renders — it just says nothing it was
        // not given.
        Setting::query()->where('group', 'contact')
            ->where('key', 'like', 'dock_%')
            ->update(['value' => json_encode(null)]);

        app(Settings::class)->forget();

        $this->get($this->campaignPath())->assertOk()->assertSee('class="dock"', false);
    }
}
