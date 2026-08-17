<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An inbox the site talks to, and what it talks to that inbox about.
 *
 * The table's docblock explains why this stopped being an env variable. This
 * class holds the one rule that must not live in a controller: how the site
 * decides who to tell, including what it does when nobody has been configured
 * yet.
 */
class NotificationRecipient extends Model
{
    /** The events a recipient can subscribe to — the approved scope, entire. */
    public const EVENT_NEW_LEAD = 'new_lead';

    public const EVENT_CRM_FAILURE = 'crm_failure';

    public const EVENT_DAILY_SUMMARY = 'daily_summary';

    /** @var list<string> */
    public const EVENTS = [
        self::EVENT_NEW_LEAD,
        self::EVENT_CRM_FAILURE,
        self::EVENT_DAILY_SUMMARY,
    ];

    protected $fillable = [
        'email', 'name', 'is_active',
        'on_new_lead', 'on_crm_failure', 'on_daily_summary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'on_new_lead' => 'boolean',
            'on_crm_failure' => 'boolean',
            'on_daily_summary' => 'boolean',
        ];
    }

    /** The column holding the subscription flag for an event. */
    public static function columnFor(string $event): string
    {
        return 'on_'.$event;
    }

    /** @param  Builder<self>  $query */
    public function scopeSubscribedTo(Builder $query, string $event): void
    {
        $query->where('is_active', true)->where(self::columnFor($event), true);
    }

    /**
     * The addresses to notify about one event.
     *
     * WHY IT CAN FALL BACK TO THE ENV VARIABLE
     *
     * This table starts empty. On the deployment where it first appears there
     * is a working `LEADS_NOTIFY_TO` and nobody has opened the new screen yet,
     * and an enquiry arriving in that window is exactly the enquiry that must
     * not go unseen — a lead is the site's only metric (§1) and there is no
     * second chance at a first response.
     *
     * So an entirely empty table means "not configured yet", and the env value
     * still stands. The moment ANY recipient exists the table is the whole
     * answer, including the case where none of them wanted this event: that is
     * a decision somebody made on the screen, and silently adding the old
     * address back would override it invisibly.
     *
     * @return list<string>
     */
    public static function emailsFor(string $event): array
    {
        if (! in_array($event, self::EVENTS, true)) {
            return [];
        }

        if (self::query()->count() === 0) {
            return array_values(config('site.leads.notify_to', []));
        }

        return self::query()
            ->subscribedTo($event)
            ->orderBy('sort_order')
            ->pluck('email')
            ->all();
    }
}
