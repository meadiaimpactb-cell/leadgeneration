<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Campaign pages (§9.1).
 *
 * "Create marketing campaign pages with complete ease" is called out as
 * critical because the campaigns team, not a developer, will be the ones
 * using it. Creating a campaign therefore scaffolds the whole page — hero,
 * three value points, trust proof, lead field — so the team starts from a
 * working landing page and only has to fill in the copy.
 */
class CampaignController extends Controller
{
    /** The starting structure a new campaign gets (§11.3). */
    private const TEMPLATE_SECTIONS = ['hero', 'cards', 'logos', 'contact_block'];

    public function index(): Response
    {
        Gate::authorize('viewAny', Campaign::class);

        return Inertia::render('Admin/Campaigns/Index', [
            'campaigns' => Campaign::query()
                ->with('translations')
                ->withCount('leads')
                ->latest('id')
                ->get()
                ->map(fn (Campaign $campaign): array => [
                    'id' => $campaign->id,
                    'slug' => $campaign->slug,
                    'title' => $campaign->t('title'),
                    'isActive' => $campaign->is_active,
                    'isLive' => $campaign->isLive(),
                    'startsAt' => $campaign->starts_at?->toDateString(),
                    'endsAt' => $campaign->ends_at?->toDateString(),
                    'leads' => $campaign->leads_count,
                    'url' => url(app()->getLocale().'/c/'.$campaign->slug),
                    'previewUrl' => url(app()->getLocale().'/c/'.$campaign->slug).'?preview='.$campaign->preview_token,
                ]),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Campaign::class);

        return Inertia::render('Admin/Campaigns/Edit', [
            'campaign' => null,
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function edit(Campaign $campaign): Response
    {
        Gate::authorize('update', $campaign);

        return Inertia::render('Admin/Campaigns/Edit', [
            'campaign' => $this->payload($campaign),
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Campaign::class);

        $campaign = new Campaign(['created_by' => $request->user()->id]);

        DB::transaction(function () use ($request, $campaign): void {
            $this->persist($request, $campaign);
            $this->scaffold($campaign);
        });

        // Straight into the section builder: step 2 of the 3-step wizard.
        return redirect()
            ->route('admin.sections.index', ['campaign', $campaign->id])
            ->with('success', __('admin.campaign_created'));
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        Gate::authorize('update', $campaign);

        $this->persist($request, $campaign);

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('delete', $campaign);

        $campaign->delete();

        return redirect()
            ->route('admin.campaigns.index')
            ->with('success', __('admin.deleted'));
    }

    // ---------------------------------------------------------------- //

    private function persist(Request $request, Campaign $campaign): void
    {
        $rules = [
            'slug' => ['required', 'string', 'max:191'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'default_utm_source' => ['nullable', 'string', 'max:128'],
            'default_utm_medium' => ['nullable', 'string', 'max:128'],
            'default_utm_campaign' => ['nullable', 'string', 'max:128'],
        ];

        foreach (array_keys(config('site.locales')) as $locale) {
            $rules["translations.{$locale}.title"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.meta_title"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.meta_description"] = ['nullable', 'string', 'max:512'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($campaign, $data): void {
            $campaign->fill([
                'slug' => Str::slug($data['slug'], '-', null),
                'is_active' => (bool) ($data['is_active'] ?? false),
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'default_utm_source' => $data['default_utm_source'] ?? null,
                'default_utm_medium' => $data['default_utm_medium'] ?? null,
                'default_utm_campaign' => $data['default_utm_campaign'] ?? null,
            ])->save();

            foreach ($data['translations'] ?? [] as $locale => $values) {
                if (blank($values['title'] ?? null)) {
                    $campaign->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $campaign->translations()->updateOrCreate(['locale' => $locale], $values);
            }
        });
    }

    private function scaffold(Campaign $campaign): void
    {
        foreach (self::TEMPLATE_SECTIONS as $position => $type) {
            $campaign->sections()->create([
                'type' => $type,
                'sort_order' => $position,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Campaign $campaign): array
    {
        $translations = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $row = $campaign->translationFor($locale);

            $translations[$locale] = [
                'title' => $row?->title,
                'meta_title' => $row?->meta_title,
                'meta_description' => $row?->meta_description,
            ];
        }

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'is_active' => $campaign->is_active,
            'starts_at' => $campaign->starts_at?->toDateString(),
            'ends_at' => $campaign->ends_at?->toDateString(),
            'default_utm_source' => $campaign->default_utm_source,
            'default_utm_medium' => $campaign->default_utm_medium,
            'default_utm_campaign' => $campaign->default_utm_campaign,
            'previewUrl' => url(app()->getLocale().'/c/'.$campaign->slug).'?preview='.$campaign->preview_token,
            'translations' => $translations,
        ];
    }
}
