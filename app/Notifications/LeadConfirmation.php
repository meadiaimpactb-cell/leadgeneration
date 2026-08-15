<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The reply the sender gets, so an enquiry does not vanish into silence (§6.2).
 *
 * WHY IT CAN DECIDE NOT TO SEND
 *
 * The wording of this message is marketing copy in Amad Craft's voice, and
 * §22.1 forbids inventing any. So the subject and body live in `settings` and
 * are written by the client — and until they are, this notification sends
 * NOTHING. An empty shell email signed with the company's name would be worse
 * than no email at all.
 *
 * WHY EMAIL ONLY
 *
 * The form accepts an email address or a mobile number, whichever the visitor
 * prefers (§6.1). A number can only be answered by SMS, which needs a paid
 * gateway that is neither contracted nor configured — so a phone-only enquiry
 * gets no automatic reply, and the team's own alert is what covers it. Saying
 * that plainly beats pretending every sender is confirmed.
 */
class LeadConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /**
     * Nothing at all unless there is both an address to answer and something
     * written to say.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->body() === null ? [] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->subject() ?? '')
            ->greeting('');

        /*
         * Each paragraph as the client typed it, and nothing appended. Laravel's
         * default outro ("Regards, …") is suppressed by `salutation('')` — an
         * automatic sign-off in the wrong language under a hand-written Arabic
         * message reads as a machine, which is the impression this email exists
         * to avoid.
         */
        foreach (preg_split('/\R{2,}/u', $this->body() ?? '') ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph !== '') {
                $message->line($paragraph);
            }
        }

        return $message->salutation('');
    }

    /** The client's subject line for the lead's own language. */
    private function subject(): ?string
    {
        return $this->setting('subject');
    }

    /** The client's message body for the lead's own language. */
    private function body(): ?string
    {
        return $this->setting('body');
    }

    /**
     * Read in the language the visitor used, never falling back to the other.
     *
     * §12 is explicit: serving Arabic to someone who wrote in English is worse
     * than serving nothing. Here that means an English enquiry gets no reply
     * until the English text exists, rather than an Arabic one.
     */
    private function setting(string $key): ?string
    {
        $value = app(Settings::class)->get("leads.confirmation.{$key}.{$this->lead->locale}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
