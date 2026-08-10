<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Page;
use Illuminate\Support\Carbon;
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
    public function __invoke(): Response
    {
        $since = now()->subDays(30)->startOfDay();
        $previousSince = now()->subDays(60)->startOfDay();

        $total = Lead::query()->count();
        $recent = Lead::query()->where('created_at', '>=', $since)->count();
        $previous = Lead::query()
            ->whereBetween('created_at', [$previousSince, $since])
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total' => $total,
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
                'drafts' => Page::query()->where('status', 'draft')->count(),
                'liveCampaigns' => Campaign::query()->live()->count(),
            ],

            'daily' => $this->daily($since),
            'bySource' => $this->bySource($since),
            'byCampaign' => $this->byCampaign($since),

            'latest' => Lead::query()
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
                ]),

            'crmDriver' => config('crm.driver'),
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
