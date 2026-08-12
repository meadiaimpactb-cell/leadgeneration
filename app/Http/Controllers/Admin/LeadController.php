<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PushLeadToCrm;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadField;
use App\Rules\InternationalPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The leads list (§11.4).
 *
 * Filters by date, campaign, source, status and CRM state; a row opens a side
 * panel with the full detail, the UTM set and the CRM sync log; the whole
 * filtered set exports at any time (§9.1).
 */
class LeadController extends Controller
{
    public function index(Request $request, ?Lead $lead = null): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $leads = $this->filtered($request)
            ->with('campaign')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $row): array => $this->row($row));

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'filters' => $request->only(['from', 'to', 'campaign', 'source', 'status', 'crm', 'q', 'interest']),
            'statuses' => Lead::STATUSES,
            'campaigns' => Campaign::query()->orderBy('slug')->get()
                ->map(fn (Campaign $c): array => ['id' => $c->id, 'slug' => $c->slug]),
            'sources' => Lead::query()
                ->whereNotNull('utm_source')
                ->distinct()
                ->orderBy('utm_source')
                ->pluck('utm_source'),
            /*
             * Read from the leads themselves rather than from a fixed list.
             * The tag is set by a section setting in the panel, so the day a
             * second page grows its own pair of audiences the filter offers
             * them without a developer.
             */
            'interests' => Lead::query()
                ->whereNotNull('interest')
                ->distinct()
                ->orderBy('interest')
                ->pluck('interest'),
            'can' => [
                'updateStatus' => $request->user()->can('update', $lead ?? new Lead),
                'export' => $request->user()->can('export', Lead::class),
            ],
            // The side panel is part of the list page, so a deep link to one
            // lead still renders the list behind it (§11.4).
            'selected' => $lead?->exists ? $this->detail($lead) : null,
        ]);
    }

    /**
     * The same screen as index(), with one lead's side panel open.
     */
    public function show(Request $request, Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        return $this->index($request, $lead);
    }

    /** @return array<string, mixed> */
    private function detail(Lead $lead): array
    {
        $lead->loadMissing(['campaign', 'crmSyncLogs']);

        return array_merge($this->row($lead), [
            'pageUrl' => $lead->page_url,
            'referrer' => $lead->referrer,
            'locale' => $lead->locale,
            'utm' => [
                'source' => $lead->utm_source,
                'medium' => $lead->utm_medium,
                'campaign' => $lead->utm_campaign,
                'term' => $lead->utm_term,
                'content' => $lead->utm_content,
                'gclid' => $lead->gclid,
                'fbclid' => $lead->fbclid,
            ],
            'sectorHint' => $lead->sector_hint,
            'extra' => $this->readableExtra($lead),
            'crmProvider' => $lead->crm_provider,
            'crmExternalId' => $lead->crm_external_id,
            'crmSyncedAt' => $lead->crm_synced_at?->toIso8601String(),
            'syncLogs' => $lead->crmSyncLogs->map(fn ($log): array => [
                'id' => $log->id,
                'provider' => $log->provider,
                'attempt' => $log->attempt,
                'httpStatus' => $log->http_status,
                'error' => $log->error,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Lead::STATUSES)],
        ]);

        $lead->forceFill(['status' => $data['status']])->save();

        return back()->with('success', __('admin.saved'));
    }

    /**
     * Re-queue a lead that never reached the CRM. §6.3 requires that a failed
     * sync is recoverable without a developer.
     */
    public function resync(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $lead->forceFill(['crm_status' => Lead::CRM_PENDING])->save();
        PushLeadToCrm::dispatch($lead->id);

        return back()->with('success', __('admin.resync_queued'));
    }

    /**
     * Export the current filter set (§9.1).
     *
     * Streamed rather than built in memory: an export must not fall over once
     * the site has done its job and there are 50,000 leads.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Lead::class);

        $query = $this->filtered($request)->with('campaign');
        $filename = 'amadcraft-leads-'.now()->format('Y-m-d-Hi').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            // BOM so Excel opens Arabic as UTF-8 rather than mojibake.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'التاريخ', 'وسيلة التواصل', 'النوع', 'الرسالة', 'الحالة',
                'حالة CRM', 'الحملة', 'المصدر', 'الوسيط', 'الحملة الإعلانية',
                'القطاع', 'الاهتمام', 'اللغة', 'الصفحة', 'المُحيل',
                // Answers to any field enabled beyond §6.1's two. One column,
                // so enabling a field never breaks an existing import.
                'حقول إضافية',
            ]);

            $query->chunk(500, function ($leads) use ($out): void {
                foreach ($leads as $lead) {
                    fputcsv($out, [
                        $lead->created_at?->format('Y-m-d H:i'),
                        $lead->contact_value,
                        $lead->contact_type,
                        $lead->message,
                        $lead->status,
                        $lead->crm_status,
                        $lead->campaign?->slug,
                        $lead->utm_source,
                        $lead->utm_medium,
                        $lead->utm_campaign,
                        $lead->sector_hint,
                        $lead->interest,
                        $lead->locale,
                        $lead->page_url,
                        $lead->referrer,
                        $this->formatExtra($lead),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * A lead's extra answers, written for a person rather than for a dialler.
     *
     * Phone numbers are STORED in E.164 — `+966512345678` — because that is
     * what a CRM and a WhatsApp link need. Nobody reads a number that way, so
     * the screen shows `+966 51 234 5678`. The stored value is untouched;
     * this is only how it is displayed, and the two must never swap places.
     *
     * @return array<string, mixed>
     */
    private function readableExtra(Lead $lead): array
    {
        $phoneKeys = LeadField::query()->where('type', 'tel')->pluck('key')->all();

        return collect($lead->extra ?? [])
            ->map(fn ($value, string $key): mixed => in_array($key, $phoneKeys, true) && is_string($value)
                ? InternationalPhone::readable($value)
                : $value)
            ->all();
    }

    /**
     * Flatten a lead's extra answers into one readable cell.
     */
    private function formatExtra(Lead $lead): string
    {
        return collect($this->readableExtra($lead))
            ->map(fn ($value, string $key): string => $key.': '.(is_bool($value) ? ($value ? 'نعم' : 'لا') : $value))
            ->implode(' · ');
    }

    private function filtered(Request $request): Builder
    {
        return Lead::query()
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('campaign'), fn (Builder $q) => $q->where('campaign_id', $request->integer('campaign')))
            ->when($request->filled('source'), fn (Builder $q) => $q->where('utm_source', $request->string('source')->toString()))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('crm'), fn (Builder $q) => $q->where('crm_status', $request->string('crm')->toString()))
            ->when($request->filled('interest'), fn (Builder $q) => $q->where('interest', $request->string('interest')->toString()))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('contact_value', 'like', $term)
                    ->orWhere('message', 'like', $term));
            });
    }

    /** @return array<string, mixed> */
    private function row(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'contact' => $lead->contact_value,
            'type' => $lead->contact_type,
            'message' => $lead->message,
            'status' => $lead->status,
            'crmStatus' => $lead->crm_status,
            'campaign' => $lead->campaign?->slug,
            'source' => $lead->utm_source,
            // In the row, not only in the side panel: on a page with two
            // audiences this is what decides who picks the enquiry up, and a
            // fact you must open a drawer to learn is one nobody sorts by.
            'interest' => $lead->interest,
            'createdAt' => $lead->created_at?->toIso8601String(),
        ];
    }
}
