<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\SectorResource;
use App\Http\Resources\SolutionResource;
use App\Models\Sector;
use App\Models\Solution;
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
}
