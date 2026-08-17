<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Page;
use Database\Seeders\LeadFieldsSeeder;
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
    // The toast
    // ---------------------------------------------------------------- //

    /**
     * The confirmation is an announcement, not a dialogue.
     *
     * It replaced a modal that covered the work the visitor had just done and
     * asked them to click to acknowledge something they had already watched
     * happen. `role="status"` with `aria-live="polite"` is how a screen reader
     * is told without focus being taken from the form.
     */
    #[Test]
    public function nothing_on_the_page_blocks_it_any_more(): void
    {
        $html = $this->home();

        // The toast itself is only drawn once something has happened, so what
        // is checkable here is the absence of the thing it replaced: no
        // scrim, no modal semantics, nothing that takes the page hostage.
        $this->assertStringNotContainsString('dialog__scrim', $html);
        $this->assertStringNotContainsString('aria-modal', $html);
    }

    /**
     * Announced rather than demanded.
     *
     * Asserted against the component rather than the page because the toast
     * renders only after a submission — but these two attributes are the
     * whole difference between a screen reader being told and a keyboard user
     * being trapped, so they are worth a guard that does not depend on
     * driving a browser.
     */
    #[Test]
    public function the_confirmation_is_announced_not_forced(): void
    {
        $source = file_get_contents(resource_path('js/Components/ui/Toast.vue'));

        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringNotContainsString('aria-modal', $source);

        // It leaves on its own, and reading it does not cost you the chance
        // to finish reading it.
        $this->assertStringContainsString('@mouseenter="hold"', $source);
        $this->assertStringContainsString("event.key === 'Escape'", $source);
    }

    /**
     * The toast carries the Sadu edge the sections carry — the detail that
     * makes it part of this site rather than a borrowed component. Recorded
     * as the ninth and last home of the thread in components.css.
     */
    #[Test]
    public function the_confirmation_is_dressed_in_the_sites_own_thread(): void
    {
        $css = file_get_contents(resource_path('css/components.css'));

        $this->assertStringContainsString("9. ui/Toast's edge", $css,
            'a use of the Sadu thread that is not in the inventory is a use nobody agreed to');
        $this->assertStringContainsString('Do not add a tenth.', $css);
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
