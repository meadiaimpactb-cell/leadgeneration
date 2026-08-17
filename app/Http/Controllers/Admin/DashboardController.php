<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The internal measurement panel (§14.2, §21).
 *
 * It reports the one thing this site is measured on: leads received, their
 * quality, and how fast they were answered. It deliberately reports no sales
 * figures — nothing is sold here (§14.2).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /*
         * §9.2: authorisation is enforced here, never by hiding a button — and
         * this screen used to enforce none at all.
         *
         * Every admin role reaches this route; only some of them may see
         * enquiries. `editor` is content-only and carries no `leads.view` at
         * all (RolesSeeder), yet the panel answered 200 and handed it the
         * eight most recent leads complete with each buyer's email address and
         * the text of their message. Measured, with an editor account: status
         * 200, contact leaked, message leaked.
         *
         * Institutional buyers' contact details are the most sensitive thing
         * this site holds, and a content editor was never meant to have them.
         * So the figures and the list are assembled only for someone entitled
         * to them; everyone else gets a dashboard without a leads section,
         * which is the honest shape of what they may see.
         */
        $maySeeLeads = $request->user()->can('leads.view');

        $since = now()->subDays(30)->startOfDay();

        return Inertia::render('Admin/Dashboard', [
            'maySeeLeads' => $maySeeLeads,
            'stats' => $this->stats($maySeeLeads, $since),

            'daily' => $maySeeLeads ? $this->daily($since) : [],
            'bySource' => $maySeeLeads ? $this->bySource($since) : [],
            'byCampaign' => $maySeeLeads ? $this->byCampaign($since) : [],

            'latest' => $maySeeLeads ? $this->latest() : [],

            'crmDriver' => config('crm.driver'),
        ]);
    }

    /**
     * The figures, split by who may see what.
     *
     * The two that are not about enquiries — draft pages and live campaigns —
     * stay for everyone: an editor needs to know what is unpublished, and
     * neither number says anything about a buyer.
     *
     * @return array<string, mixed>
     */
    private function stats(bool $maySeeLeads, Carbon $since): array
    {
        $always = [
            'drafts' => Page::query()->where('status', 'draft')->count(),
            'liveCampaigns' => Campaign::query()->live()->count(),
        ];

        if (! $maySeeLeads) {
            return $always;
        }

        $previousSince = now()->subDays(60)->startOfDay();

        $recent = Lead::query()->where('created_at', '>=', $since)->count();
        $previous = Lead::query()
            ->whereBetween('created_at', [$previousSince, $since])
            ->count();

        return $always + [
            'total' => Lead::query()->count(),
            'recent' => $recent,
            // Direction of travel, not a target — §21 defers target
            // numbers until after launch.
            'change' => $previous > 0
                ? round((($recent - $previous) / $previous) * 100)
                : null,
            'qualified' => Lead::query()->whereIn('status', ['qualified', 'won'])->count(),
            'pendingCrm' => Lead::query()->notSynced()->count(),
            'failedCrm' => Lead::query()->where('crm_status', Lead::CRM_FAILED)->count(),
            'unanswered' => Lead::query()->where('status', 'new')->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function latest()
    {
        return Lead::query()
            ->with('campaign')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'contact' => $lead->contact_value,
                'type' => $lead->contact_type,
                'message' => $lead->message,
                'status' => $lead->status,
                'crmStatus' => $lead->crm_status,
                'campaign' => $lead->campaign?->slug,
                'createdAt' => $lead->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Leads per day, with empty days filled in — a gap in the series would
     * otherwise read as "no data" rather than "no leads".
     *
     * @return list<array{date: string, count: int}>
     */
    private function daily(Carbon $since): array
    {
        $counts = Lead::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($date = $since->copy(); $date->lte(now()); $date->addDay()) {
            $key = $date->toDateString();
            $series[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $series;
    }

    /** @return list<array{label: string, count: int}> */
    private function bySource(Carbon $since): array
    {
        return Lead::query()
            ->where('created_at', '>=', $since)
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'direct') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->label, 'count' => (int) $row->total])
            ->all();
    }

    /** @return list<array{label: string, count: int}> */
    private function byCampaign(Carbon $since): array
    {
        return Lead::query()
            ->where('leads.created_at', '>=', $since)
            ->whereNotNull('campaign_id')
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->select('campaigns.slug as label', DB::raw('COUNT(*) as total'))
            ->groupBy('campaigns.slug')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => ['label' => (string) $row->label, 'count' => (int) $row->total])
            ->all();
    }
}
