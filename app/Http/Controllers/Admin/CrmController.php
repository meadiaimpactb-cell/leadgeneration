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

        /*
         * The ceiling is per field kind, not one number for all of them.
         *
         * A flat max:512 rejected the only credential that matters here: Zid's
         * access token is a JWT of well over a thousand characters, so pasting
         * the real one failed validation and the connection could never be
         * saved — the error the client was looking at.
         */
        foreach (CrmSettings::FIELDS as $provider => $fields) {
            foreach ($fields as $field => $type) {
                $max = CrmSettings::MAX_LENGTH[$type] ?? 512;

                $rules["credentials.{$provider}.{$field}"] = ['nullable', 'string', "max:{$max}"];
            }
        }

        $data = $request->validate($rules);

        Setting::query()->updateOrCreate(
            ['group' => 'crm', 'key' => 'driver'],
            ['value' => $data['driver']],
        );

        foreach (CrmSettings::FIELDS as $provider => $fields) {
            foreach ($fields as $field => $type) {
                $value = $data['credentials'][$provider][$field] ?? null;

                /*
                 * The redaction placeholder means "unchanged", not "set the
                 * key to a row of bullets". Without this, opening the screen
                 * and pressing save would overwrite every secret with dots
                 * and take the integration down.
                 */
                if (CrmSettings::isSecret($type) && $value === '••••••••') {
                    continue;
                }

                /*
                 * A token copied out of a dashboard arrives with whitespace on
                 * it more often than not — a trailing newline from the copy
                 * button, a space from a double-click. Sent as-is it produces a
                 * 401 that looks exactly like a wrong token, and the client
                 * re-copies a token that was right the first time.
                 */
                if (is_string($value)) {
                    $value = trim($value);
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
     *
     * It used to stop at `isConfigured()` — four non-empty boxes — and report
     * "fully configured", which a client reasonably reads as "it works". A
     * mistyped token, an expired one and one belonging to a different store all
     * passed that test, so the screen said yes on a connection that would drop
     * every lead. It now calls the provider and reports what came back.
     */
    public function test(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        $driver = app(CrmManager::class)->driver();

        if (! $driver->isConfigured()) {
            return back()->with('error', __('admin.crm_test_unconfigured', ['driver' => $driver->name()]));
        }

        $result = $driver->verify();

        if (! $result->success) {
            return back()->with('error', __('admin.crm_verify_failed', [
                'driver' => $driver->name(),
                'error' => $result->error ?? '—',
            ]));
        }

        // Which account answered, when the provider named one. "Connected" on
        // its own cannot catch a token pointing at the wrong store.
        return back()->with('success', $result->externalId !== null
            ? __('admin.crm_verify_ok_as', ['driver' => $driver->name(), 'account' => $result->externalId])
            : __('admin.crm_verify_ok', ['driver' => $driver->name()]));
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
