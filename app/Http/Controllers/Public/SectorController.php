<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\SectorResource;
use App\Http\Resources\SolutionResource;
use App\Models\Sector;
use App\Models\Solution;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A sector page (§11.2): the four audience segments from §3.
 *
 * Breadcrumb › focused hero › what we offer › media split › sector impact
 * numbers › client logos › FAQ › CTA band.
 */
class SectorController extends PublicController
{
    public function show(string $locale, string $slug): Response
    {
        $sector = Sector::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'translations',
                'media',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translations', 'media'])
                    ->orderBy('sort_order'),
                'impactMetrics.translations',
                'clients.translations',
                'clients.media',
            ])
            ->first();

        // A sector with no translation in this locale is genuinely absent
        // here — never fall back to Arabic for an English visitor (§12).
        if ($sector === null || ! $sector->hasTranslation($locale)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Public/Sector', [
            'sector' => SectorResource::make($sector),
            'sections' => $this->sections($sector),

            'solutions' => SolutionResource::collection(
                Solution::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->limit(3)->get()
            ),

            'impact' => ImpactMetricResource::collection($sector->impactMetrics),
            'clients' => PartnerResource::collection($sector->clients),

            /*
             * The other segments, and this one's place among them.
             *
             * Both come from the same ordered query: a visitor who has read
             * this far and is on the wrong page should find the right one
             * without going back to the menu, and the running number has to
             * match the order the menu shows.
             */
            'siblings' => SectorResource::collection(
                $this->siblings($locale)->reject(fn (Sector $s): bool => $s->is($sector))->values()
            ),
            'index' => $this->siblings($locale)->search(fn (Sector $s): bool => $s->is($sector)) + 1,
            'total' => $this->siblings($locale)->count(),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $sector->t('name'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $sector->t('meta_title') ?: $sector->t('name'),
                'description' => $sector->t('meta_description') ?: $sector->t('summary'),
                'image' => $sector->getFirstMediaUrl('hero') ?: null,
            ], $sector->translatedLocales()),
        ]);
    }

    /**
     * Every segment the visitor could be on, in menu order.
     *
     * Memoised for the request: this is asked three times in one render —
     * for the list, for this page's position, and for the total — and
     * `preventLazyLoading` is on, so three separate queries would be three
     * chances to differ.
     *
     * @return Collection<int, Sector>
     */
    private function siblings(string $locale): Collection
    {
        return $this->siblings ??= Sector::query()
            ->visible()
            ->translatedIn($locale)
            ->withTranslation()
            ->with('media')
            ->get();
    }

    /** @var Collection<int, Sector>|null */
    private ?Collection $siblings = null;
}
