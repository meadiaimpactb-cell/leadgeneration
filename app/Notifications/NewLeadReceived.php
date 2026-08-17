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
 * Tells the Amad Craft team a lead has arrived (§6.2 step 3).
 *
 * Response time is one of the four success metrics (§21.4), so this carries
 * everything needed to act — contact, message, and where they came from —
 * rather than a "you have a new lead, log in to see it" stub.
 */
class NewLeadReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Lead $lead) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('notifications.alert_greeting'));

        /*
         * The client's own opening line, if they wrote one — a place to put a
         * standing instruction ("answer within a working day", "copy the
         * Riyadh office") without a developer. Absent, the mail simply starts
         * with the enquiry, which is what it did before.
         */
        if (($intro = $this->setting('alert_intro')) !== null) {
            $mail->line($intro);
        }

        $mail->line('**'.($lead->isEmail() ? 'البريد' : 'الجوال').':** '.$lead->contact_value)
            ->line('**الرسالة:** '.($lead->message ?? '—'));

        $source = collect([
            'الصفحة' => $lead->page_url,
            'الحملة' => $lead->campaign?->slug ?? $lead->utm_campaign,
            'المصدر' => $lead->utm_source,
            'الوسيط' => $lead->utm_medium,
            'القطاع' => $lead->sector_hint,
            // sponsor / trainee — the first thing that decides who picks this
            // enquiry up, so it belongs in the alert and not only in the panel.
            'الاهتمام' => $lead->interest,
            'المُحيل' => $lead->referrer,
        ])->filter()->map(fn ($v, $k): string => "{$k}: {$v}");

        if ($source->isNotEmpty()) {
            $mail->line('---')->line($source->implode(' · '));
        }

        return $mail
            ->action('فتح في لوحة التحكم', url('/admin/leads/'.$lead->id))
            ->salutation('أمد الحرف');
    }

    /**
     * The client's subject line, or the shipped one.
     *
     * `:contact` is substituted either way, so a rewritten subject keeps the
     * one piece of information that makes a list of these triageable.
     */
    private function subject(): string
    {
        $written = $this->setting('alert_subject');

        return $written === null
            ? __('notifications.alert_subject', ['contact' => $this->lead->contact_value])
            : str_replace(':contact', $this->lead->contact_value, $written);
    }

    /** A panel-written value, or null when it has not been written. */
    private function setting(string $key): ?string
    {
        $value = app(Settings::class)->get("notifications.{$key}");

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_uuid' => $this->lead->uuid,
            'contact' => $this->lead->contact_value,
        ];
    }
}
