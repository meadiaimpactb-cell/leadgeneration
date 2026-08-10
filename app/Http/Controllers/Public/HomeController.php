<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\SectorResource;
use App\Http\Resources\SolutionResource;
use App\Http\Resources\StoryResource;
use App\Models\ImpactMetric;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Sector;
use App\Models\Solution;
use App\Models\Story;
use App\Services\Seo\MetaBuilder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page (§11.1).
 *
 * Every string it renders comes from the database. This controller assembles
 * containers; it never supplies copy (§0.1, §22.1).
 */
class HomeController extends PublicController
{
    public function __invoke(): Response
    {
        return $this->index();
    }

    public function index(): Response
    {
        $locale = app()->getLocale();

        $page = Page::query()
            ->where('slug', 'home')
            ->with(['translation', 'sections' => fn ($q) => $q->where('is_active', true)
                ->with(['translation', 'media'])
                ->orderBy('sort_order')])
            ->first();

        // A draft home page is visible only through its own secret link (§9.1).
        $previewing = $page !== null && $this->isPreviewing($page);

        if ($page !== null && ! $page->isPublished() && ! $previewing) {
            $page = null;
        }

        return Inertia::render('Public/Home', [
            'previewing' => $previewing,
            'page' => $page === null ? null : [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],

            'sections' => $page?->sections->map(fn ($section): array => [
                'id' => $section->id,
                'type' => $section->type,
                'settings' => array_merge(
                    $section->settings ?? [],
                    array_filter([
                        'image' => $section->imagePayload(),
                        'images' => $section->galleryPayload() ?: null,
                    ])
                ),
                'heading' => $section->t('heading'),
                'subheading' => $section->t('subheading'),
                'body' => $section->t('body'),
                'ctaLabel' => $section->t('cta_label'),
                'ctaUrl' => $section->t('cta_url'),
            ])->values() ?? [],

            'solutions' => SolutionResource::collection(
                Solution::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->get()
            ),

            'sectors' => SectorResource::collection(
                Sector::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->get()
            ),

            'impact' => ImpactMetricResource::collection(
                ImpactMetric::query()->visible()->global()->translatedIn($locale)
                    ->withTranslation()->get()
            ),

            'stories' => StoryResource::collection(
                Story::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->limit(8)->get()
            ),

            'partners' => PartnerResource::collection(
                Partner::query()->visible()->translatedIn($locale)
                    ->whereIn('type', [Partner::TYPE_PARTNER, Partner::TYPE_ACCREDITATION])
                    ->withTranslation()->with('media')->get()
            ),

            'seo' => app(MetaBuilder::class)->build([
                'title' => $page?->t('title'),
                'description' => $page?->t('meta_description'),
                'robots' => $previewing ? 'noindex, nofollow' : null,
            ]),
        ]);
    }
}
