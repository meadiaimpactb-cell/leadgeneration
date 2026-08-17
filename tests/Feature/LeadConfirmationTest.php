<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadField;
use App\Models\Setting;
use App\Notifications\LeadConfirmation;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The reply the sender gets (§6.2).
 *
 * What these tests are really about is the silence. This notification must
 * send nothing until the client has written the words, and nothing in the
 * wrong language ever: a confirmation email is the first thing an
 * institutional buyer receives from Amad Craft, and an empty one — or an
 * Arabic one answering an English enquiry — is worse than none at all.
 */
class LeadConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The confirmation is about wording, not about the form's shape, so the
     * demo lead form's extra required fields are cleared here — a submission
     * carrying only a contact would otherwise be rejected before any
     * notification could be sent. See LeadCaptureTest for the same note.
     */
    protected function setUp(): void
    {
        parent::setUp();

        LeadField::query()->delete();
    }

    /** The client, writing the wording in the panel. */
    private function wrote(string $locale, string $subject, string $body): void
    {
        foreach (['subject' => $subject, 'body' => $body] as $key => $value) {
            Setting::query()->updateOrCreate(
                ['group' => 'leads', 'key' => "confirmation.{$key}.{$locale}"],
                ['value' => $value],
            );
        }

        // A query-builder write does not fire the model events the settings
        // cache listens to.
        app(Settings::class)->forget();
    }

    private function lead(string $locale, string $type = Lead::TYPE_EMAIL): Lead
    {
        return Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'contact_value' => $type === Lead::TYPE_EMAIL ? 'buyer@ministry.test' : '+966512345678',
            'contact_type' => $type,
            'locale' => $locale,
            'status' => 'new',
            'crm_status' => Lead::CRM_PENDING,
        ]);
    }

    #[Test]
    public function nothing_is_sent_until_the_client_has_written_the_words(): void
    {
        Notification::fake();

        $this->from('/ar')->post('/leads', [
            'contact' => 'buyer@ministry.test',
            'message' => 'استفسار',
        ])->assertRedirect();

        Notification::assertNotSentTo(new AnonymousNotifiable, LeadConfirmation::class);
    }

    #[Test]
    public function it_is_sent_to_the_sender_once_the_wording_exists(): void
    {
        $this->wrote('ar', 'وصل استفسارك', "شكرًا لتواصلك.\n\nسنعود إليك.");

        Notification::fake();

        $this->from('/ar')->post('/leads', [
            'contact' => 'buyer@ministry.test',
            'message' => 'استفسار',
        ])->assertRedirect();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            LeadConfirmation::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'buyer@ministry.test',
        );
    }

    /**
     * §12 applied to email: Arabic wording must never answer an English
     * enquiry. The reply waits for the English text rather than falling back.
     */
    #[Test]
    public function an_english_enquiry_gets_no_arabic_reply(): void
    {
        $this->wrote('ar', 'وصل استفسارك', 'شكرًا لتواصلك.');

        $this->assertSame([], (new LeadConfirmation($this->lead('en')))->via(new AnonymousNotifiable));
    }

    #[Test]
    public function an_english_enquiry_is_answered_once_the_english_text_exists(): void
    {
        $this->wrote('en', 'We have your enquiry', 'Thank you for getting in touch.');

        $this->assertSame(['mail'], (new LeadConfirmation($this->lead('en')))->via(new AnonymousNotifiable));
    }

    /**
     * A visitor who left a mobile number and no address. There is nowhere to
     * send an email and no SMS gateway is contracted, so the team's own alert
     * is what covers this enquiry.
     */
    #[Test]
    public function a_phone_only_enquiry_gets_no_email(): void
    {
        $this->wrote('ar', 'وصل استفسارك', 'شكرًا لتواصلك.');

        Notification::fake();

        $this->from('/ar')->post('/leads', ['contact' => '+966512345678'])->assertRedirect();

        Notification::assertNotSentTo(new AnonymousNotifiable, LeadConfirmation::class);
    }

    /**
     * Whitespace is not wording. A body of blank lines would otherwise send an
     * email with the company's name on it and nothing inside.
     */
    #[Test]
    public function whitespace_does_not_count_as_written(): void
    {
        $this->wrote('ar', 'وصل استفسارك', "   \n\n  \t ");

        $this->assertSame([], (new LeadConfirmation($this->lead('ar')))->via(new AnonymousNotifiable));
    }
}
