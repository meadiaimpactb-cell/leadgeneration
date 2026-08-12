<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Page;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the form looks like, and what it does when it has been sent.
 *
 * The success state used to replace the whole form with one line of text.
 * Half the CTA band went empty and the control the visitor had just been
 * using disappeared, so a completed errand read as a broken page. The answer
 * is now a dialog over a form that stays where it is.
 *
 * §10.6 asked for the opposite — "short message + the Sadu thread weaving
 * itself underneath. No modal, no redirect." The owner overrode it after
 * seeing the empty band. Recorded here because a test that contradicts the
 * brief should say why on its face.
 */
class LeadFormAnswersInPlaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // LeadFieldsSeeder is not optional here: without it the form has no
        // fields at all, and every assertion about its shape passes or fails
        // for the wrong reason.
        $this->seed([
            RolesSeeder::class,
            StructureSeeder::class,
            LeadFieldsSeeder::class,
            NavigationSeeder::class,
            DemoContentSeeder::class,
        ]);

        Page::query()->update(['status' => 'published', 'published_at' => now()]);
    }

    private function home(): string
    {
        return $this->get('/ar')->assertOk()->getContent();
    }

    /**
     * A submission the shipped configuration actually accepts.
     *
     * §6.1's form is one field, but the client turned company and phone on
     * and made all three required — so a test that posts only `contact` is
     * testing a form this site does not have.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function submission(string $email, array $extra = []): array
    {
        return array_merge([
            'organisation' => 'وزارة الثقافة',
            'phone' => '0551234567',
            'contact' => $email,
        ], $extra);
    }

    // ---------------------------------------------------------------- //
    // Order
    // ---------------------------------------------------------------- //

    /**
     * Company, phone, email, message, then the button.
     *
     * The message box used to sit after the button, which asked the visitor
     * to notice a field below the thing that ends the form.
     */
    #[Test]
    public function the_message_box_sits_between_the_last_field_and_the_button(): void
    {
        $html = $this->home();

        $message = mb_strpos($html, 'lead-textarea');
        $submit = mb_strpos($html, 'lead__submit');

        $this->assertNotFalse($message, 'the optional message box is on the page');
        $this->assertNotFalse($submit, 'the submit button is on the page');

        $this->assertLessThan($submit, $message,
            'a visitor writes their line and then presses send, not the other way round'
        );
    }

    #[Test]
    public function the_message_box_is_visible_and_optional(): void
    {
        $html = $this->home();

        $field = LeadField::query()->where('key', LeadField::KEY_MESSAGE)->firstOrFail();

        $this->assertTrue($field->is_enabled);
        $this->assertFalse($field->is_required, 'the message is never a condition of getting in touch');

        // Present as a real textarea, not behind a "add a message" link.
        $this->assertStringContainsString('lead-textarea', $html);
    }

    // ---------------------------------------------------------------- //
    // The answer
    // ---------------------------------------------------------------- //

    /**
     * The form is still in the markup after a submission, and the dialog's
     * strings are shipped with the page rather than fetched on demand.
     */
    #[Test]
    public function the_form_stays_on_the_page_after_a_successful_submission(): void
    {
        $response = $this->from('/ar')->post('/leads', $this->submission('buyer@ministry.gov.sa'));

        $response->assertRedirect('/ar');
        $response->assertSessionHas('lead_submitted', true);

        $html = $this->get('/ar')->assertOk()->getContent();

        // The controls are all still there — nothing was replaced by a notice.
        $this->assertStringContainsString('lead__submit', $html);
        $this->assertStringContainsString('name="contact"', $html);
        $this->assertStringContainsString('lead-textarea', $html);
    }

    /**
     * One locale per test, one request each.
     *
     * Asserting both inside one test does not work: within a single test
     * process the second request comes back in the first request's language.
     * That is an artefact of the container living across requests — SetLocale
     * reads the URL prefix and nothing caches the strings, and a real /en
     * request serves English — but a test that depends on request order is
     * testing the harness, not the site.
     */
    #[Test]
    public function the_arabic_dialog_strings_reach_the_page(): void
    {
        $this->assertDialogStringsFor('ar');
    }

    #[Test]
    public function the_english_dialog_strings_reach_the_page(): void
    {
        $this->assertDialogStringsFor('en');
    }

    private function assertDialogStringsFor(string $locale): void
    {
        // Decoded first: "We've received your request" can reach the page with
        // its apostrophe escaped, and a raw match would fail on punctuation
        // rather than on the string being absent.
        $html = html_entity_decode(
            $this->get("/{$locale}")->assertOk()->getContent(),
            ENT_QUOTES | ENT_HTML5
        );

        foreach (['success_title', 'success_body', 'error_title', 'error_body'] as $key) {
            $string = trans("leads.{$key}", locale: $locale);

            $this->assertNotSame("leads.{$key}", $string, "leads.{$key} is missing in {$locale}");
            $this->assertStringContainsString($string, $html,
                "the {$locale} dialog string reaches the page");
        }
    }

    /** A dialog nobody can dismiss without a mouse is not accessible (§10.8). */
    #[Test]
    public function the_dialog_ships_its_dismiss_label(): void
    {
        $html = $this->home();

        $this->assertStringContainsString(trans('common.done', locale: 'ar'), $html);
    }

    // ---------------------------------------------------------------- //
    // Still a lead
    // ---------------------------------------------------------------- //

    /** Both paths through the form still produce a lead the panel can see. */
    #[Test]
    public function a_request_arrives_with_or_without_a_message(): void
    {
        $this->from('/ar')->post('/leads', $this->submission('first@ministry.gov.sa'))
            ->assertSessionHas('lead_submitted', true);

        $this->from('/ar')->post('/leads', $this->submission('second@ministry.gov.sa', [
            'message' => 'عندنا مؤتمر في الرابع عشر',
        ]))->assertSessionHas('lead_submitted', true);

        $this->assertSame(2, Lead::query()->count());
        $this->assertSame(
            'عندنا مؤتمر في الرابع عشر',
            Lead::query()->where('contact_value', 'second@ministry.gov.sa')->value('message')
        );
    }
}
