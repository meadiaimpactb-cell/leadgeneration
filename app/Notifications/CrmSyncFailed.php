<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Raised when a lead could not be handed to the CRM (§6.3).
 *
 * This is an operational alert, not a marketing message: it exists so a real
 * prospect never sits unnoticed in the database because an integration broke.
 */
class CrmSyncFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Lead $lead,
        public readonly string $reason,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('⚠️ لم يصل عميل محتمل إلى الـ CRM — '.$this->lead->contact_value)
            ->greeting('تنبيه تشغيلي')
            ->line('وصل عميل محتمل إلى الموقع لكنه لم يُسجَّل في نظام إدارة العلاقات.')
            ->line('**وسيلة التواصل:** '.$this->lead->contact_value)
            ->line('**المزوّد:** '.($this->lead->crm_provider ?? config('crm.driver')))
            ->line('**السبب:** '.$this->reason)
            ->line('الطلب محفوظ في قاعدة بيانات الموقع ولم يُفقد. يلزم متابعته يدويًا وإصلاح الربط.')
            ->action('فتح الطلب', url('/admin/leads/'.$this->lead->id))
            ->salutation('أمد الحرف');
    }
}
