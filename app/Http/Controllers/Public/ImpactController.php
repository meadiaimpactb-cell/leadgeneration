<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\StoryResource;
use App\Models\ImpactMetric;
use App\Models\Report;
use App\Models\Story;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impact and reports (§5): numbers, artisan stories, publishable reports.
 */
class ImpactController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('impact');

        return Inertia::render('Public/Impact', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'impact' => ImpactMetricResource::collection(
                ImpactMetric::query()->visible()->global()->translatedIn($locale)
                    ->withTranslation()->get()
            ),

            'stories' => StoryResource::collection(
                Story::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->get()
            ),

            'reports' => ReportResource::collection(
                Report::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->orderByDesc('year')->get()
            ),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $page->t('meta_title') ?: $page->t('title'),
                'description' => $page->t('meta_description'),
                // A draft reachable by URL must never reach search results.
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ]),
        ]);
    }
}
