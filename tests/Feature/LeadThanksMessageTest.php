<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The thank-you shown on the page the moment a form is sent (§6.2 step 4).
 *
 * It used to be a string in resources/lang, which made the sentence every
 * enquirer reads the one piece of the reply Amad Craft could not change. The
 * confirmation email beside it was already theirs; this is the other half.
 *
 * WHAT THESE TESTS COVER AND WHAT THEY CANNOT
 *
 * The choice between the client's wording and the shipped default is made in
 * Vue, in the browser, and PHPUnit never runs it. So these tests hold the
 * boundary instead: they prove the written text is delivered to the browser,
 * that an unwritten one is delivered as nothing at all — which is precisely
 * what the component falls back on — and that the Arabic text is never
 * delivered to an English page (§12). The rendering itself was checked live.
 */
class LeadThanksMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesSeeder::class,
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
        ]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    /** The client, typing the wording into the panel. */
    private function wrote(string $locale, string $title, string $body): void
    {
        foreach (['title' => $title, 'body' => $body] as $key => $value) {
            Setting::query()->updateOrCreate(
                ['group' => 'leads', 'key' => "thanks.{$key}.{$locale}"],
                ['value' => $value],
            );
        }

        // A query-builder write does not fire the model events the settings
        // cache listens to.
        app(Settings::class)->forget();
    }

    /** Everything the browser is handed on a public page. */
    private function shared(string $locale = 'ar'): array
    {
        return $this->get("/{$locale}")->assertOk()->viewData('page')['props']['settings'];
    }

    /**
     * Public is a deliberate choice, not an oversight.
     *
     * The confirmation email's wording stays private because it is read on the
     * server when the mail is built. This one is drawn in the browser with no
     * further round trip, and it is text shown to every visitor anyway — so a
     * private flag here would simply mean the message never appears.
     */
    #[Test]
    public function the_four_keys_ship_as_structure_and_reach_the_browser(): void
    {
        foreach (['title.ar', 'body.ar', 'title.en', 'body.en'] as $key) {
            $setting = Setting::query()
                ->where('group', 'leads')->where('key', "thanks.{$key}")->sole();

            $this->assertTrue($setting->is_public, "leads.thanks.{$key} must be public");
            $this->assertNull($setting->value, "leads.thanks.{$key} must ship unwritten (§22.1)");
        }
    }

    #[Test]
    public function what_the_client_writes_is_what_the_browser_is_given(): void
    {
        $this->wrote('ar', 'وصل طلبكم', 'يتواصل معكم فريق المبيعات خلال يوم عمل.');

        $shared = $this->shared('ar');

        $this->assertSame('وصل طلبكم', $shared['leads.thanks.title.ar']);
        $this->assertSame('يتواصل معكم فريق المبيعات خلال يوم عمل.', $shared['leads.thanks.body.ar']);
    }

    /**
     * Unwritten, the browser receives null — and null is exactly what makes
     * the form fall back to its own wording rather than showing an empty
     * dialog. A page that goes silent after a submit reads as a page that
     * broke, which is why this one message falls back where the email does not.
     */
    #[Test]
    public function an_unwritten_thank_you_is_delivered_as_nothing_at_all(): void
    {
        $shared = $this->shared('ar');

        $this->assertArrayHasKey('leads.thanks.title.ar', $shared);
        $this->assertNull($shared['leads.thanks.title.ar']);
        $this->assertNull($shared['leads.thanks.body.ar']);
    }

    /**
     * §12, applied to four words: someone who wrote in English is never
     * answered in Arabic. The English page is handed no Arabic text to fall
     * back to, so it falls back to the English default instead.
     */
    #[Test]
    public function the_arabic_wording_never_reaches_the_english_page(): void
    {
        $this->wrote('ar', 'وصل طلبكم', 'يتواصل معكم فريق المبيعات.');

        $shared = $this->shared('en');

        $this->assertNull($shared['leads.thanks.title.en']);
        $this->assertNull($shared['leads.thanks.body.en']);
    }

    /**
     * The fields have to be reachable by the person who writes them: a key in
     * the registry that never draws an input is a setting nobody can edit.
     */
    #[Test]
    public function the_panel_offers_all_four_beside_the_confirmation_email(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super-admin');

        $keys = collect(
            $this->actingAs($user)->get('/admin/integrations/confirmations')
                ->assertOk()->viewData('page')['props']['fields']
        )->pluck('key')->all();

        foreach ([
            'leads.thanks.title.ar', 'leads.thanks.body.ar',
            'leads.thanks.title.en', 'leads.thanks.body.en',
        ] as $key) {
            $this->assertContains($key, $keys);
        }

        // The email it now shares a screen with, still there.
        $this->assertContains('leads.confirmation.body.ar', $keys);
    }
}
