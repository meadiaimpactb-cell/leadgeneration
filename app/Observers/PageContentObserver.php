<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\AnalysePageKeywords;
use App\Models\Page;
use App\Models\PageKeyword;
use App\Models\PageTranslation;
use App\Models\Section;
use App\Models\SectionTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Anything that changes what a page says re-scores that page's keywords.
 *
 * Four models can do it, and all four are wired here rather than in each
 * model, so the set is visible in one place:
 *
 *   · Page             — the slug is scored
 *   · PageTranslation  — title, meta title, meta description, excerpt
 *   · Section          — its settings hold card and step copy
 *   · SectionTranslation — heading, subheading, body
 *
 * A section reaches its page through the polymorphic owner and is ignored
 * when that owner is a Sector or a Solution: those have keywords of their own
 * (`sector_translations.meta_keywords`) but no per-keyword analysis yet, and
 * dispatching for them would queue a job that finds nothing to do.
 *
 * Deletes count as changes. Removing the section that contained a phrase must
 * turn that keyword red — a stale green is the one outcome that makes the
 * whole screen untrustworthy.
 */
class PageContentObserver
{
    public function saved(Model $model): void
    {
        $this->dispatch($model);
    }

    public function deleted(Model $model): void
    {
        $this->dispatch($model);
    }

    private function dispatch(Model $model): void
    {
        [$pageId, $locale] = match (true) {
            $model instanceof Page => [$model->id, null],
            $model instanceof PageTranslation => [$model->page_id, $model->locale],
            $model instanceof Section => [$this->pageOf($model), null],
            // Fetched rather than read through a relation: SectionTranslation
            // declares none, and `preventLazyLoading` is on in local — an
            // implicit load here would throw inside a save.
            $model instanceof SectionTranslation => [
                $this->pageOf(Section::query()->find($model->section_id)),
                $model->locale,
            ],
            default => [null, null],
        };

        if ($pageId === null) {
            return;
        }

        /*
         * Nothing to do for the overwhelming majority of saves. One `exists`
         * beats waking the analyser for every page and section a seeder
         * touches, and it is what keeps this observer free on a site where
         * nobody has entered a keyword yet.
         */
        $watched = PageKeyword::query()
            ->where('page_id', $pageId)
            ->when($locale !== null, fn ($q) => $q->where('locale', $locale))
            ->exists();

        if (! $watched) {
            return;
        }

        /*
         * Run now, not queued.
         *
         * QUEUE_CONNECTION is `database` here and a worker is a separate
         * process. Queueing this cost an afternoon to diagnose: the page
         * saved, the job sat in the table, and the score stayed exactly where
         * it was — which is the one failure this whole screen cannot survive,
         * because a stale colour is indistinguishable from a wrong one.
         *
         * The work is a few selects and some string comparison over the
         * keywords of a single page. It is not worth an ops dependency, and
         * the job class stays a job so a command or a worker can still run it.
         */
        AnalysePageKeywords::dispatchSync($pageId, $locale);
    }

    /** The page a section belongs to, or null when it belongs to something else. */
    private function pageOf(?Section $section): ?int
    {
        if ($section === null || $section->sectionable_type !== Page::class) {
            return null;
        }

        return $section->sectionable_id;
    }
}
