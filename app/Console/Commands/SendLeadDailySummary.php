<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\NotificationRecipient;
use App\Notifications\LeadDailySummary;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * The daily digest of the approved scope, sent to whoever asked for it.
 *
 * Scheduled in routes/console.php. Running it by hand is how the client checks
 * the wording without waiting until tomorrow morning.
 */
class SendLeadDailySummary extends Command
{
    protected $signature = 'amad:daily-summary {--since=1 : How many days back to count arrivals}';

    protected $description = 'Email the daily lead summary to every recipient subscribed to it';

    public function handle(): int
    {
        $recipients = NotificationRecipient::emailsFor(NotificationRecipient::EVENT_DAILY_SUMMARY);

        /*
         * Nobody subscribed is a valid answer, not an error.
         *
         * The digest is opt-in per recipient, and the scheduler runs whether
         * or not anyone wants it. Exiting 0 keeps a quiet morning out of the
         * failure logs, where a real fault would then be harder to notice.
         */
        if ($recipients === []) {
            $this->info('No recipient is subscribed to the daily summary.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('since'));

        $arrived = Lead::query()->where('created_at', '>=', now()->subDays($days))->count();

        /*
         * Both of these are all-time, not per-period, on purpose: an enquiry
         * that has sat unanswered for a week is the one worth reporting, and a
         * window would be exactly what hides it.
         *
         * Archived enquiries are excluded, and only here. Archiving is what
         * this panel has instead of deleting, so a lead that carries
         * `archived_at` is one somebody has already dealt with — counting it
         * every morning produced a figure that could never fall, and a figure
         * that never falls is one the team learns to skip. `arrived` above and
         * the dashboard's own totals still count everything that ever came in,
         * for the reason Lead::scopeArchived gives: §1 is measured on enquiries
         * received, and that number must not drop when someone tidies a list.
         */
        $awaiting = Lead::query()->notArchived()->where('status', 'new')->count();
        $notSynced = Lead::query()->notArchived()->notSynced()->count();

        Notification::route('mail', $recipients)->notify(new LeadDailySummary(
            arrived: $arrived,
            awaiting: $awaiting,
            notSynced: $notSynced,
            subject: $this->subject(),
        ));

        $this->info(sprintf(
            'Summary queued for %d recipient(s): %d arrived, %d awaiting, %d not synced.',
            count($recipients), $arrived, $awaiting, $notSynced,
        ));

        return self::SUCCESS;
    }

    /** The client's subject line, or the shipped one until they write theirs. */
    private function subject(): string
    {
        $written = app(Settings::class)->get('notifications.summary_subject');

        return is_string($written) && trim($written) !== ''
            ? trim($written)
            : __('notifications.summary_subject');
    }
}
