<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\ReportResource;
use App\Http\Resources\StoryResource;
use App\Models\ImpactMetric;
use App\Models\Report;
use App\Models\Story;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impact and reports (§5): numbers, artisan stories, publishable reports.
 *
 * The proof page. Every other page on this site makes a claim and points here,
 * so the governing rule is narrower than elsewhere: no assertion without
 * something behind it, and no number that is not read from the one record
 * holding it.
 */
class ImpactController extends PublicController
{
    /** @var Collection<int, ImpactMetric>|null */
    private ?Collection $metrics = null;

    /** @var Collection<int, Story>|null */
    private ?Collection $stories = null;

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

            /*
             * The same query the home page runs — deliberately, and guarded by
             * ImpactFiguresHaveOneSourceTest. A figure that appears in two
             * places and is stored in two places will disagree in one of them,
             * and this is the page a government buyer copies into their own
             * report. One row, read twice.
             */
            'impact' => ImpactMetricResource::collection($this->metrics($locale)),

            /*
             * Derived from when the figures were last edited, not a field
             * someone maintains beside them. The report cards taught this
             * lesson on the same page: two places to state one fact is how a
             * 2025 report ends up wearing a 2023 badge.
             */
            'measuredAt' => $this->metrics($locale)->max('updated_at')
                ?->locale($locale)->translatedFormat('F Y'),

            /*
             * Three on the page, the rest behind «كل القصص». `limit` would
             * make the button lie about how many there are.
             */
            'stories' => StoryResource::collection($this->latestStories($locale)->take(3)),
            'storiesTotal' => $this->latestStories($locale)->count(),
            'storiesUrl' => url($locale.'/impact/stories'),

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

    /** Every story, for when there are more than the three on /impact. */
    public function stories(string $locale): Response
    {
        [$page] = $this->requirePage('impact');

        return Inertia::render('Public/Stories', [
            'stories' => StoryResource::collection($this->latestStories($locale)),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => url($locale.'/impact')],
                ['label' => __('impact.all_stories'), 'url' => null],
            ]),

            'seo' => $this->seo(['title' => __('impact.all_stories')]),
        ]);
    }

    public function story(string $locale, string $slug): Response
    {
        $story = Story::query()->visible()->translatedIn($locale)
            ->withTranslation()->with('media')
            ->where('slug', $slug)->firstOrFail();

        [$page] = $this->requirePage('impact');

        return Inertia::render('Public/Story', [
            'story' => StoryResource::make($story),

            'breadcrumbs' => $this->breadcrumbs([
                ['label' => __('common.home'), 'url' => url($locale)],
                ['label' => $page->t('title'), 'url' => url($locale.'/impact')],
                ['label' => $story->t('title'), 'url' => null],
            ]),

            'seo' => $this->seo([
                'title' => $story->t('title'),
                // The quote is the story's own summary, in the artisan's words.
                'description' => $story->t('quote'),
            ]),
        ]);
    }

    /**
     * Memoised: three separate reads of the same figures in one request would
     * be three queries, and `preventLazyLoading` would not catch it because
     * each one is eager and correct on its own.
     *
     * @return Collection<int, ImpactMetric>
     */
    private function metrics(string $locale): Collection
    {
        return $this->metrics ??= ImpactMetric::query()
            ->visible()->global()->translatedIn($locale)
            ->withTranslation()->get();
    }

    /** @return Collection<int, Story> */
    private function latestStories(string $locale): Collection
    {
        return $this->stories ??= Story::query()
            ->visible()->translatedIn($locale)
            ->withTranslation()->with('media')->get();
    }
}
