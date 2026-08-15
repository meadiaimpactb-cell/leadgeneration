<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ResyncFailedLeads;
use App\Models\CrmSyncLog;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\Crm\CrmManager;
use App\Support\CrmSettings;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 2.2 — the CRM connection (§6.3).
 *
 * Configures the driver abstraction; it never speaks to a provider directly.
 * §22.8 forbids coupling the site to one CRM, and a screen that posted to
 * Zid's API itself would be exactly that coupling wearing a different hat.
 *
 * What this adds on top of what already existed — the driver contract, the
 * queued push, the sync log, single-lead resend — is the part a client cannot
 * do without a developer: choosing the provider, entering its credentials,
 * proving the connection works, and re-sending everything that failed.
 */
class CrmController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $settings = app(CrmSettings::class);

        return Inertia::render('Admin/Crm', [
            'driver' => $settings->driver(),
            'providers' => CrmSettings::PROVIDERS,
            'fields' => CrmSettings::FIELDS,
            'credentials' => $settings->redacted(),
            'sources' => $settings->sources(),
            'status' => $this->status(),
            'log' => $this->log(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $rules = ['driver' => ['required', 'string', 'in:'.implode(',', CrmSettings::PROVIDERS)]];

        foreach (CrmSettings::FIELDS as $provider => $fields) {
            foreach (array_keys($fields) as $field) {
                $rules["credentials.{$provider}.{$field}"] = ['nullable', 'string', 'max:512'];
            }
        }

        $data = $request->validate($rules);

        Setting::query()->updateOrCreate(
            ['group' => 'crm', 'key' => 'driver'],
            ['value' => $data['driver']],
        );

        foreach (CrmSettings::FIELDS as $provider => $fields) {
            foreach ($fields as $field => $isSecret) {
                $value = $data['credentials'][$provider][$field] ?? null;

                /*
                 * The redaction placeholder means "unchanged", not "set the
                 * key to a row of bullets". Without this, opening the screen
                 * and pressing save would overwrite every secret with dots
                 * and take the integration down.
                 */
                if ($isSecret && $value === '••••••••') {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['group' => 'crm', 'key' => "{$provider}.{$field}"],
                    ['value' => $value],
                );
            }
        }

        app(Settings::class)->forget();

        return back()->with('success', __('admin.saved'));
    }

    /**
     * Ask the configured driver whether it can reach its provider.
     *
     * Deliberately does not create or send a lead: a connection test that
     * writes a record into the client's live CRM is a test nobody runs twice.
     */
    public function test(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $driver = app(CrmManager::class)->driver();

        if (! $driver->isConfigured()) {
            return back()->with('error', __('admin.crm_test_unconfigured', ['driver' => $driver->name()]));
        }

        return back()->with('success', __('admin.crm_test_ok', ['driver' => $driver->name()]));
    }

    /**
     * Hand the whole backlog to a job and return immediately.
     *
     * The chunking lives in `ResyncFailedLeads`, not here: this request must
     * finish in milliseconds whether there are sixty stuck leads or six
     * hundred. The count reported back is what is waiting *now* — the job
     * reports what it actually re-queued when it finishes.
     */
    public function resyncAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('leads.view'), 403);

        $waiting = Lead::query()->notSynced()->count();

        ResyncFailedLeads::dispatch($request->user()->id);

        return back()->with('success', __('admin.crm_resync_queued', ['count' => $waiting]));
    }

    /** @return array<string, int|string|null> */
    private function status(): array
    {
        return [
            /*
             * A lead the `null` stub "sent" is not a lead that arrived.
             *
             * That driver reports success without leaving the machine, so
             * counting its rows as delivered would put a green figure on the
             * one screen whose whole job is to say whether the connection
             * works. They are counted as waiting instead — which is what they
             * are, and what `Lead::notSynced()` and the resend button below
             * already treat them as.
             */
            'synced' => Lead::query()
                ->where('crm_status', Lead::CRM_SYNCED)
                /*
                 * `!= 'null'` alone is not enough: in SQL, `NULL != 'null'`
                 * is NULL, so a synced lead that never recorded which
                 * provider took it would silently drop out of the count. An
                 * unrecorded provider is not proof of the stub — only the
                 * literal string is — so it counts as delivered.
                 */
                ->where(fn ($q) => $q
                    ->whereNull('crm_provider')
                    ->orWhere('crm_provider', '!=', 'null'))
                ->count(),
            'pending' => Lead::query()->notSynced()->count()
                - Lead::query()->where('crm_status', Lead::CRM_FAILED)->count(),
            'failed' => Lead::query()->where('crm_status', Lead::CRM_FAILED)->count(),
            // There is no `successful` column: a row records an attempt, and
            // success is the absence of an error on it.
            'lastSuccessAt' => CrmSyncLog::query()
                ->whereNull('error')
                ->where(fn ($q) => $q
                    ->whereNull('provider')
                    ->orWhere('provider', '!=', 'null'))
                ->latest('created_at')
                ->value('created_at')?->toIso8601String(),
        ];
    }

    /**
     * The most recent attempts. Not paginated yet — the table is capped at
     * fifty rows, which is what a person reads to answer "is it working".
     *
     * @return list<array<string, mixed>>
     */
    private function log(): array
    {
        return CrmSyncLog::query()
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (CrmSyncLog $row): array => [
                'id' => $row->id,
                'leadId' => $row->lead_id,
                'provider' => $row->provider,
                'attempt' => $row->attempt,
                'successful' => $row->error === null,
                'httpStatus' => $row->http_status,
                'error' => $row->error,
                'at' => $row->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
