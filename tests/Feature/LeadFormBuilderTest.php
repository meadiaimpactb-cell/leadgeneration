<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use Database\Seeders\LeadFieldsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The admin-managed lead form (§6.1, §9.1).
 *
 * The form's shape is data, so Amad Craft can change it without a developer.
 * Two things are NOT data and are asserted here:
 *
 *   · the contact field is always present and always required — a lead with
 *     no way to reach the person is not a lead (§1)
 *   · the shipped configuration is exactly §6.1: contact + optional message,
 *     and no name
 */
class LeadFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();
        Cache::flush();
    }

    #[Test]
    public function the_shipped_form_is_exactly_the_two_fields_the_brief_defines(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        $enabled = LeadField::query()->enabled()->pluck('key')->all();

        $this->assertSame(['contact', 'message'], $enabled);
    }

    #[Test]
    public function a_name_field_exists_but_ships_switched_off(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        // §6.1: "Name — never asked". The capability is available to the
        // client; the default is the brief's.
        $name = LeadField::query()->where('key', 'name')->sole();

        $this->assertFalse($name->is_enabled);
    }

    #[Test]
    public function the_contact_field_cannot_be_switched_off(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        $contact = LeadField::query()->where('key', LeadField::KEY_CONTACT)->sole();

        $this->assertTrue($contact->is_locked);
        $this->assertTrue($contact->is_required);
    }

    #[Test]
    public function the_contact_value_is_required_even_with_no_field_rows_at_all(): void
    {
        // The regression this guards: rules were derived from lead_fields, so
        // an empty table produced an endpoint that accepted anything and then
        // crashed on a null contact.
        $this->assertSame(0, LeadField::query()->count());

        $this->from('/ar')
            ->post('/leads', [LeadField::KEY_CONTACT => ''])
            ->assertSessionHasErrors(LeadField::KEY_CONTACT);

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function a_lead_still_stores_with_no_field_rows_at_all(): void
    {
        $this->post('/leads', [LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa']);

        $this->assertSame('buyer@ministry.gov.sa', Lead::sole()->contact_value);
    }

    #[Test]
    public function an_enabled_extra_field_is_captured_onto_the_lead(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        LeadField::query()->where('key', 'organisation')->update(['is_enabled' => true]);
        Cache::flush();

        $this->post('/leads', [
            LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa',
            'organisation' => 'وزارة الثقافة',
        ]);

        // Extra answers live in JSON, so enabling a field never needs a
        // migration.
        $this->assertSame(['organisation' => 'وزارة الثقافة'], Lead::sole()->extra);
    }

    #[Test]
    public function a_disabled_field_is_ignored_even_if_it_is_posted(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        // `name` ships disabled. Posting it anyway must not store it.
        $this->post('/leads', [
            LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa',
            'name' => 'فلان',
        ]);

        $this->assertNull(Lead::sole()->extra);
    }

    #[Test]
    public function a_required_extra_field_is_enforced(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        LeadField::query()->where('key', 'organisation')
            ->update(['is_enabled' => true, 'is_required' => true]);
        Cache::flush();

        $this->from('/ar')
            ->post('/leads', [LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa'])
            ->assertSessionHasErrors('organisation');

        $this->assertSame(0, Lead::query()->count());
    }

    #[Test]
    public function a_select_field_only_accepts_its_own_options(): void
    {
        $this->seed(LeadFieldsSeeder::class);

        LeadField::query()->where('key', 'sector')->update(['is_enabled' => true]);
        Cache::flush();

        $this->from('/ar')
            ->post('/leads', [
                LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa',
                'sector' => 'not-a-real-sector',
            ])
            ->assertSessionHasErrors('sector');

        $this->post('/leads', [
            LeadField::KEY_CONTACT => 'buyer@ministry.gov.sa',
            'sector' => 'government',
        ]);

        $this->assertSame(['sector' => 'government'], Lead::sole()->extra);
    }
}
