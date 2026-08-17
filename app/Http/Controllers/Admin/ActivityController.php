<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Who did what, and when (§9.1, §15.3) — read only, on purpose.
 *
 * WHY THERE IS NO DELETE BUTTON
 *
 * An audit trail somebody can edit is not an audit trail. The screen offers
 * no way to remove a row, and this controller exposes no route that could:
 * the value of the record is precisely that the person whose action it
 * describes cannot reach it. Old rows are pruned by Spatie's own scheduled
 * command against a retention period, never by hand from here.
 *
 * WHY THE SUBJECT IS SHOWN AS A TYPE AND AN ID
 *
 * A logged row outlives the thing it describes — deleting a page is exactly
 * the event most worth auditing, and after it there is nothing left to load.
 * So the row renders from what it stored rather than resolving the subject,
 * and a deleted page still reads as "page #14 was deleted by X".
 */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        /*
         * Deliberately narrower than the rest of the panel.
         *
         * This screen shows every administrator's actions to whoever opens
         * it, including the failed sign-in attempts against their colleagues'
         * accounts. That is a supervisory view, not a content one, so it sits
         * behind settings.manage rather than pages.view — an editor being
         * able to read the trail of everyone above them is not what §9.1's
         * tiered permissions describe.
         */
        abort_unless($request->user()->can('settings.manage'), 403);

        $entries = $this->filtered($request)
            ->with('causer')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Activity $row): array => [
                'id' => $row->id,
                'at' => $row->created_at?->toIso8601String(),
                'event' => $row->event ?? $row->description,
                'log' => $row->log_name,
                // The name if the account still exists; null renders as the
                // system itself, which is what a scheduled command is.
                'causer' => $row->causer?->name,
                'subjectType' => $row->subject_type === null
                    ? null
                    : class_basename($row->subject_type),
                'subjectId' => $row->subject_id,
                'properties' => $this->readable($row),
            ]);

        return Inertia::render('Admin/Activity', [
            'entries' => $entries,
            'filters' => $request->only(['user', 'event', 'log', 'from', 'to']),
            // Offered from what has actually been recorded, not a fixed list:
            // a model added to the audit trait next month filters without a
            // developer, and an event nobody has ever caused is not offered.
            'events' => Activity::query()
                ->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'logs' => Activity::query()
                ->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name'),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** @return Builder<Activity> */
    private function filtered(Request $request): Builder
    {
        return Activity::query()
            ->when($request->filled('user'), fn (Builder $q) => $q
                ->where('causer_type', User::class)
                ->where('causer_id', $request->integer('user')))
            ->when($request->filled('event'), fn (Builder $q) => $q
                ->where('event', $request->string('event')->toString()))
            ->when($request->filled('log'), fn (Builder $q) => $q
                ->where('log_name', $request->string('log')->toString()))
            ->when($request->filled('from'), fn (Builder $q) => $q
                ->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q
                ->whereDate('created_at', '<=', $request->date('to')));
    }

    /**
     * What changed, as a list of field names — never their values.
     *
     * The trail answers "who touched this, and which parts of it". Rendering
     * the values themselves would print an enquirer's contact details and a
     * settings key's contents onto a screen more people can open than can
     * open the records those came from. `Setting` already logs no values at
     * all; this is the same rule applied at the point of display, so a model
     * added to the audit trait later cannot leak by being forgotten here.
     *
     * @return array<string, mixed>
     */
    private function readable(Activity $activity): array
    {
        $properties = $activity->properties;

        // Rows that carry their own summary — a settings key, an export's row
        // count, the email a failed sign-in used — pass through as they are.
        // None of them holds a value worth hiding; that is why they were
        // written this way at the point of logging.
        $plain = $properties->only(['key', 'rows', 'filters', 'email'])->all();

        $changed = array_keys((array) $properties->get('attributes', []));

        return $plain + ($changed === [] ? [] : ['fields' => $changed]);
    }
}
