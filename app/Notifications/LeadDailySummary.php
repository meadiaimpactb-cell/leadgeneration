<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One message a day for the people who should not get one per enquiry.
 *
 * WHY IT REPORTS WHAT IS STILL UNANSWERED
 *
 * A count of yesterday's leads is a number a manager can already read off the
 * dashboard. What they cannot see without looking is the queue building up
 * behind it — enquiries that arrived and were never opened. Response time is
 * one of the four success metrics (§21.4), and the only version of this email
 * worth sending is one that makes an unanswered enquiry harder to leave
 * unanswered.
 *
 * The CRM line is here for the same reason. A silently failing integration
 * looks exactly like a quiet week.
 */
class LeadDailySummary extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $arrived  enquiries received in the period
     * @param  int  $awaiting  enquiries still at status `new`, all time
     * @param  int  $notSynced  enquiries that have not reached a CRM, all time
     */
    public function __construct(
        public readonly int $arrived,
        public readonly int $awaiting,
        public readonly int $notSynced,
        public readonly string $subject,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting(__('notifications.summary_greeting'))
            ->line(__('notifications.summary_arrived', ['count' => $this->arrived]))
            ->line(__('notifications.summary_awaiting', ['count' => $this->awaiting]));

        /*
         * Only when there is something wrong. A line reading "0 failed" every
         * morning is how a reader learns to skip the paragraph the one morning
         * it does not say zero.
         */
        if ($this->notSynced > 0) {
            $mail->line(__('notifications.summary_not_synced', ['count' => $this->notSynced]));
        }

        return $mail
            ->action(__('notifications.summary_action'), url('/admin/leads'))
            ->salutation('');
    }
}
