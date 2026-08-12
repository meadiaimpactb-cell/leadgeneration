<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Page;
use App\Models\User;
use App\Rules\InternationalPhone;
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
 * The form's two hard fields: a number that can be dialled and an address
 * that can be written to.
 *
 * The browser checks both — intl-tel-input for the number, a real pattern for
 * the address — but the browser is not where a lead becomes real. Everything
 * here goes through the endpoint, because a crafted POST and a visitor with
 * JavaScript off arrive at exactly the same place (§7.4).
 */
class LeadFieldQualityTest extends TestCase
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

    /** @param  array<string, string>  $overrides */
    private function send(array $overrides = [])
    {
        return $this->from('/ar')->post('/leads', array_merge([
            'organisation' => 'وزارة الثقافة',
            'phone' => '+966512345678',
            'contact' => 'buyer@ministry.gov.sa',
        ], $overrides));
    }

    // ---------------------------------------------------------------- //
    // The number
    // ---------------------------------------------------------------- //

    /**
     * Stored as it is dialled, not as it was typed.
     *
     * «0512345678» is how a Saudi writes their own number. It means nothing
     * to a WhatsApp link, and a sales team that has to rewrite every number
     * before calling it will eventually call the wrong one.
     */
    #[Test]
    public function a_local_number_is_stored_in_international_form(): void
    {
        $this->send(['phone' => '0512345678'])->assertSessionHas('lead_submitted', true);

        $this->assertSame('+966512345678', Lead::sole()->extra['phone']);
    }

    /**
     * The number exactly as the field displays it, spaces and all.
     *
     * intl-tel-input formats as you type — «+966 54 232 7104» — and that is
     * the string the browser now sends when the model never caught the
     * keystrokes. It has to be accepted and normalised, not refused for its
     * spaces.
     */
    #[Test]
    public function a_number_submitted_as_it_appears_on_screen_is_accepted(): void
    {
        $this->send(['phone' => '+966 54 232 7104'])->assertSessionHas('lead_submitted', true);

        $this->assertSame('+966542327104', Lead::sole()->extra['phone']);
    }

    #[Test]
    public function a_number_from_another_country_keeps_its_own_country(): void
    {
        $this->send(['phone' => '+49 30 901820'])->assertSessionHas('lead_submitted', true);

        $this->assertSame('+4930901820', Lead::sole()->extra['phone'],
            'a German number must not be re-read as a Saudi one');
    }

    #[Test]
    public function letters_in_the_number_are_refused(): void
    {
        $this->send(['phone' => 'call me maybe'])->assertSessionHasErrors('phone');

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function a_number_of_the_wrong_length_for_its_country_is_refused(): void
    {
        // Six digits is not a Saudi mobile, and length alone cannot tell you
        // that — the country's own numbering rules can.
        $this->send(['phone' => '051234'])->assertSessionHasErrors('phone');

        $this->send(['phone' => '+9665123456789012'])->assertSessionHasErrors('phone');
    }

    #[Test]
    public function the_leads_screen_shows_the_number_the_way_a_person_reads_it(): void
    {
        $this->send(['phone' => '0512345678']);

        $admin = User::query()->create([
            'name' => 'Sales',
            'email' => 'sales@amadcraft.test',
            'password' => Hash::make('correct-horse-battery-1!'),
            'is_active' => true,
        ]);

        $admin->assignRole(User::ROLE_SUPER_ADMIN);

        $lead = Lead::sole();

        $html = $this->actingAs($admin)->get("/admin/leads/{$lead->id}")->assertOk()->getContent();

        // Stored one way, shown another — and the stored value is untouched.
        $this->assertSame('+966512345678', $lead->fresh()->extra['phone']);
        $this->assertStringContainsString('+966 51 234 5678', $html);
    }

    #[Test]
    public function the_readable_form_never_replaces_the_stored_one(): void
    {
        $this->assertSame('+966512345678', InternationalPhone::e164('0512345678'));
        $this->assertSame('+966 51 234 5678', InternationalPhone::readable('0512345678'));

        // Anything unparseable comes back untouched rather than being lost.
        $this->assertSame('nonsense', InternationalPhone::e164('nonsense'));
    }

    // ---------------------------------------------------------------- //
    // The address
    // ---------------------------------------------------------------- //

    #[Test]
    public function an_address_the_browser_would_accept_but_nobody_can_reach_is_refused(): void
    {
        // `type="email"` accepts this; it has no domain to deliver to.
        $this->send(['contact' => 'buyer@ministry'])->assertSessionHasErrors('contact');
    }

    #[Test]
    public function a_public_mailbox_is_accepted(): void
    {
        // Small institutions run on Gmail. Refusing them refuses real work.
        $this->send(['contact' => 'director@gmail.com'])->assertSessionHas('lead_submitted', true);

        $this->assertSame('director@gmail.com', Lead::sole()->contact_value);
    }

    #[Test]
    public function an_address_is_stored_in_lower_case_without_spaces(): void
    {
        $this->send(['contact' => '  Buyer@Ministry.GOV.SA '])->assertSessionHas('lead_submitted', true);

        $this->assertSame('buyer@ministry.gov.sa', Lead::sole()->contact_value);
    }

    /** The same tidying applies to an email in an admin-enabled extra field. */
    #[Test]
    public function an_extra_email_field_is_tidied_too(): void
    {
        LeadField::query()->where('key', 'job_title')->update([
            'key' => 'work_email',
            'type' => 'email',
            'is_enabled' => true,
            'is_required' => false,
        ]);

        $this->send(['work_email' => '  Person@Company.SA '])->assertSessionHas('lead_submitted', true);

        $this->assertSame('person@company.sa', Lead::sole()->extra['work_email']);
    }

    // ---------------------------------------------------------------- //
    // The switch
    // ---------------------------------------------------------------- //

    /**
     * One entry: where you can go, not where you are.
     *
     * Showing both put «العربية» beside «English» on an Arabic page, one of
     * them inert, naming the language the reader can already see.
     */
    #[Test]
    public function the_arabic_page_offers_english_and_nothing_else(): void
    {
        $this->assertSwitchOffers('ar', 'en');
    }

    #[Test]
    public function the_english_page_offers_arabic_and_nothing_else(): void
    {
        $this->assertSwitchOffers('en', 'ar');
    }

    /**
     * One locale per test, one request each — a second request inside the
     * same test comes back in the first one's language. See
     * LeadFormAnswersInPlaceTest for why that is the harness and not the site.
     */
    private function assertSwitchOffers(string $on, string $to): void
    {
        $nav = $this->switchMarkup($this->get("/{$on}")->assertOk()->getContent());

        $this->assertStringContainsString("hreflang=\"{$to}\"", $nav, "the {$on} page offers {$to}");
        $this->assertStringNotContainsString("hreflang=\"{$on}\"", $nav,
            'the language being read is not offered as somewhere to go');
        $this->assertSame(1, substr_count($nav, '<a '), 'exactly one destination');
    }

    /** The switch alone — `hreflang` also appears in the head's SEO links. */
    private function switchMarkup(string $html): string
    {
        preg_match('/<nav[^>]*class="lang-switch".*?<\/nav>/s', $html, $m);

        $this->assertNotEmpty($m, 'the language switch is on the page');

        return $m[0];
    }

    // ---------------------------------------------------------------- //
    // Messages a person can read
    // ---------------------------------------------------------------- //

    /**
     * There was no validation.php in either locale, so every server-side
     * complaint rendered as its own key — a visitor who left the phone field
     * empty was told «validation.required», which reads as a broken page
     * rather than as a mistake they can fix. It affected every form on the
     * site; the lead form's own overrides in leads.php were the only messages
     * that ever spoke.
     */
    #[Test]
    public function a_rejected_field_says_why_in_the_visitors_language(): void
    {
        $response = $this->from('/ar')->post('/leads', [
            'organisation' => 'وزارة الثقافة',
            'contact' => 'buyer@ministry.gov.sa',
            // phone omitted, and it is required in this configuration
        ]);

        $errors = session('errors')->getBag('default')->get('phone');

        $this->assertNotEmpty($errors);
        $this->assertStringNotContainsString('validation.', $errors[0],
            'a raw translation key must never reach a visitor');
        $this->assertStringContainsString('رقم الهاتف', $errors[0],
            'and it names the field in Arabic');
    }

    #[Test]
    public function every_rule_the_form_uses_has_a_message(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (['required', 'email', 'max', 'string', 'boolean', 'in', 'prohibited'] as $rule) {
                $line = trans("validation.{$rule}", locale: $locale);

                $this->assertNotSame("validation.{$rule}", $line,
                    "validation.{$rule} is missing in {$locale}");
            }
        }
    }

    /** Switching language keeps you on the page you were reading (§12). */
    #[Test]
    public function switching_language_stays_on_the_same_page(): void
    {
        $html = $this->get('/ar/about')->assertOk()->getContent();

        $this->assertStringContainsString('/en/about', $html,
            'the switch must offer this page in the other language, not the home page');
    }
}
