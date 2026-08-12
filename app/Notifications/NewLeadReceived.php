<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
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
            ->subject('عميل محتمل جديد · New lead — '.$lead->contact_value)
            ->greeting('عميل محتمل جديد')
            ->line('**'.($lead->isEmail() ? 'البريد' : 'الجوال').':** '.$lead->contact_value)
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

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_uuid' => $this->lead->uuid,
            'contact' => $this->lead->contact_value,
        ];
    }
}
