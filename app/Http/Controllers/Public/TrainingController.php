<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Resources\ImpactMetricResource;
use App\Http\Resources\StoryResource;
use App\Http\Resources\TrainingProgramResource;
use App\Models\ImpactMetric;
use App\Models\Page;
use App\Models\Story;
use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Training and empowerment (§5).
 *
 * The one page with two audiences: an artisan asking to join a track, and an
 * institution asking to sponsor one. Both are served the same data — what a
 * track is, how it runs, what it has produced — and what differs is only which
 * door each of them is offered.
 *
 * The figures come from the same register /impact and the home page read, and
 * the graduate stories are the same records /impact publishes, tagged. Nothing
 * on this page is a second copy of a fact stored elsewhere.
 */
class TrainingController extends PublicController
{
    public function index(string $locale): Response
    {
        [$page, $previewing] = $this->requirePage('training');

        $metrics = $this->metrics($locale, $page);

        return Inertia::render('Public/Training', [
            'previewing' => $previewing,
            'page' => [
                'title' => $page->t('title'),
                'subtitle' => $page->t('subtitle'),
            ],
            'sections' => $this->sections($page),

            'programs' => TrainingProgramResource::collection(
                TrainingProgram::query()->visible()->translatedIn($locale)
                    ->withTranslation()->with('media')->get()
            ),

            'impact' => ImpactMetricResource::collection($metrics),

            /*
             * Derived from when the figures were last edited, exactly as on
             * /impact. A figure with no date is a claim; a figure with a date
             * is a measurement — and a second field stating it by hand is how
             * the two drift apart.
             */
            'measuredAt' => $metrics->max('updated_at')
                ?->locale($locale)->translatedFormat('F Y'),

            'graduates' => StoryResource::collection($this->graduates($locale, $page)),

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

    /**
     * The training figures — a selection from the one register, never a copy.
     *
     * Which figures appear is the `stats` section's own `keys` setting, so the
     * client chooses them in the panel; what each one says is the register's
     * business alone. A key naming a figure nobody has measured yet returns
     * nothing, because `visible()` skips a row with no value, and the band
     * closes up around it rather than showing a labelled blank.
     *
     * @return Collection<int, ImpactMetric>
     */
    private function metrics(string $locale, Page $page): Collection
    {
        $keys = $this->setting($page, 'stats', 'keys');

        // No section, or a section naming no figures, shows no figures. The
        // page must never fall back to the site-wide set: «ساعة تدريب» beside
        // «قطعة حرفية سُلّمت» would be a training claim made of delivery data.
        if (! is_array($keys) || $keys === []) {
            return new Collection;
        }

        $metrics = ImpactMetric::query()
            ->visible()->global()->translatedIn($locale)
            ->withTranslation()
            ->whereIn('key', $keys)
            ->get();

        // In the order the panel listed them, not the register's own order:
        // the client chose this sequence for this page.
        return $metrics->sortBy(
            fn (ImpactMetric $metric): int => array_search($metric->key, $keys, true),
        )->values();
    }

    /**
     * Stories tagged as graduates of these tracks.
     *
     * The strongest thing this page can put in front of an institution
     * weighing a sponsorship is what happened to the last people who were
     * trained. It is also the thing that must not be invented (§22.1), so the
     * section stays silent until Amad Craft tags a real story.
     *
     * @return Collection<int, Story>
     */
    private function graduates(string $locale, Page $page): Collection
    {
        $tag = $this->setting($page, 'story_carousel', 'tag');

        if ($tag === null || $tag === '') {
            return new Collection;
        }

        return Story::query()
            ->visible()->translatedIn($locale)->tagged($tag)
            ->withTranslation()->with('media')
            ->take(2)
            ->get();
    }

    /** One section's setting, without assuming the section is still there. */
    private function setting(Page $page, string $type, string $key): mixed
    {
        return $page->sections->firstWhere('type', $type)?->setting($key);
    }
}
