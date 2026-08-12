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
     * Both languages are offered, and the one being read is marked rather
     * than removed — a switch showing only «English» tells a visitor nothing
     * about what they are looking at now.
     */
    #[Test]
    public function the_language_switch_offers_both_and_marks_the_current_one(): void
    {
        $html = $this->get('/ar')->assertOk()->getContent();

        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
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
