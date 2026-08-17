<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who is told what, and in which words (the notifications screen, §6.2 step 3).
 *
 * The gap this closes is not cosmetic. Until now the answer to "who finds out
 * a ministry just made contact" was an env variable on the production server,
 * so §20 decision 4 — naming the person who responds — could not be acted on
 * without a deployment. The recipients table's docblock has the rest.
 *
 * The screen deliberately keeps three things in one place: the addresses, what
 * each of them is subscribed to, and the wording of the mail itself. They are
 * one decision made by one person, and splitting them across screens is how an
 * alert ends up going to somebody who is not expecting it.
 */
class NotificationController extends Controller
{
    /** Settings this screen owns; see SettingsRegistry for why they are here. */
    private const TEMPLATE_KEYS = [
        'notifications.alert_subject',
        'notifications.alert_intro',
        'notifications.summary_subject',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return Inertia::render('Admin/Integrations/Notifications', [
            'recipients' => NotificationRecipient::query()
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn (NotificationRecipient $r): array => [
                    'id' => $r->id,
                    'email' => $r->email,
                    'name' => $r->name,
                    'isActive' => $r->is_active,
                    'events' => [
                        NotificationRecipient::EVENT_NEW_LEAD => $r->on_new_lead,
                        NotificationRecipient::EVENT_CRM_FAILURE => $r->on_crm_failure,
                        NotificationRecipient::EVENT_DAILY_SUMMARY => $r->on_daily_summary,
                    ],
                ]),
            'events' => NotificationRecipient::EVENTS,
            'template' => $this->template(),
            /*
             * The state the screen must be honest about.
             *
             * With no recipient configured the site still alerts whoever is in
             * LEADS_NOTIFY_TO, and an operator looking at an empty list would
             * otherwise conclude that nobody is being told — or, worse, that
             * they are safe to leave it empty. Both readings are wrong, and
             * the second one loses leads the day someone clears the variable.
             */
            'envFallback' => NotificationRecipient::query()->count() === 0
                ? array_values(config('site.leads.notify_to', []))
                : [],
            /*
             * Mail is queued (§6.2), so a stopped worker means nothing is
             * delivered while the screen still says it was sent. The screen
             * says so rather than letting the client discover it in a week.
             */
            'queueDriver' => config('queue.default'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('notification_recipients', 'email')],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        NotificationRecipient::query()->create([
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'sort_order' => (int) NotificationRecipient::query()->max('sort_order') + 1,
        ]);

        return back()->with('success', __('admin.saved'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $data = $request->validate([
            'recipients' => ['present', 'array'],
            'recipients.*.id' => ['required', 'integer', 'exists:notification_recipients,id'],
            'recipients.*.name' => ['nullable', 'string', 'max:120'],
            'recipients.*.isActive' => ['boolean'],
            'recipients.*.events' => ['required', 'array'],
            'recipients.*.events.*' => ['boolean'],
            'template' => ['present', 'array'],
            'template.*' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['recipients'] as $position => $row) {
                $recipient = NotificationRecipient::query()->find($row['id']);

                if ($recipient === null) {
                    continue;
                }

                $recipient->fill([
                    'name' => $row['name'] ?? null,
                    'is_active' => (bool) ($row['isActive'] ?? false),
                    'sort_order' => $position,
                ]);

                foreach (NotificationRecipient::EVENTS as $event) {
                    $recipient->{NotificationRecipient::columnFor($event)}
                        = (bool) ($row['events'][$event] ?? false);
                }

                $recipient->save();
            }

            foreach ($data['template'] as $key => $value) {
                // Only the three this screen owns. A key posted from anywhere
                // else is ignored rather than trusted (§9.2).
                if (! in_array($key, self::TEMPLATE_KEYS, true)) {
                    continue;
                }

                [$group, $name] = explode('.', $key, 2);

                Setting::query()
                    ->where('group', $group)->where('key', $name)
                    ->first()?->forceFill(['value' => $value])->save();
            }
        });

        app(Settings::class)->forget();

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Request $request, NotificationRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $recipient->delete();

        return back()->with('success', __('admin.deleted'));
    }

    /** @return array<string, string|null> */
    private function template(): array
    {
        $settings = app(Settings::class);

        return collect(self::TEMPLATE_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $settings->get($key)])
            ->all();
    }
}
