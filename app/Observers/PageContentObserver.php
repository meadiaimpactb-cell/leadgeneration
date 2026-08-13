<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\AnalysePageKeywords;
use App\Models\MediaAttachment;
use App\Models\Page;
use App\Models\PageKeyword;
use App\Models\PageTranslation;
use App\Models\Section;
use App\Models\SectionItem;
use App\Models\SectionItemTranslation;
use App\Models\SectionTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Anything that changes what a page says re-scores that page's keywords.
 *
 * Seven models can do it, and all seven are wired here rather than in each
 * model, so the set is visible in one place:
 *
 *   · Page                   — the slug is scored
 *   · PageTranslation        — title, meta title, meta description, excerpt
 *   · Section                — its settings still hold card and step copy
 *   · SectionTranslation     — heading, subheading, body
 *   · SectionItem            — a card switched off leaves the page
 *   · SectionItemTranslation — the card's own words
 *   · MediaAttachment        — which images the page shows, hence which alt
 *                              texts the alt check can find
 *
 * A section reaches its page through the polymorphic owner and is ignored when
 * that owner is a Sector or a Solution: those have keywords of their own
 * (`sector_translations.meta_keywords`) but no per-keyword analysis, and
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
        [$pageId, $locale] = $this->target($model);

        if ($pageId === null) {
            return;
        }

        /*
         * Nothing to do for the overwhelming majority of saves. One `exists`
         * beats queueing a job for every page, section and card a seeder
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
         * Queued: an editor saving a page waits for the save, not for the
         * scoring of sixty phrases behind it.
         *
         * The window this opens — the score describing the page as it was a
         * moment ago — is closed by `content_hash`, which lets the screen see
         * that a row predates the current content and say so. Without that the
         * queue would be unsafe here, because a stale colour and a wrong one
         * look identical. See AnalysePageKeywords.
         */
        AnalysePageKeywords::dispatch($pageId, $locale);
    }

    /**
     * The page a save belongs to, and the locale it changed if only one did.
     *
     * Every hop is a fresh query rather than a relation: these run inside a
     * save, and `preventLazyLoading` is on in local (§7.4) — reading through
     * an unloaded relation here would throw in development.
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function target(Model $model): array
    {
        return match (true) {
            $model instanceof Page => [$model->id, null],
            $model instanceof PageTranslation => [$model->page_id, $model->locale],
            $model instanceof Section => [$this->pageOf($model), null],
            $model instanceof SectionTranslation => [
                $this->pageOf(Section::query()->find($model->section_id)),
                $model->locale,
            ],
            $model instanceof SectionItem => [$this->pageOfItem($model->section_id), null],
            $model instanceof SectionItemTranslation => [
                $this->pageOfItem(
                    SectionItem::query()->find($model->section_item_id)?->section_id
                ),
                $model->locale,
            ],
            // Attaching or detaching an image changes which alt texts the page
            // has, and the alt check is the one nobody remembers to re-run.
            $model instanceof MediaAttachment => [$this->pageOfAttachment($model), null],
            default => [null, null],
        };
    }

    /** The page a section belongs to, or null when it belongs to something else. */
    private function pageOf(?Section $section): ?int
    {
        if ($section === null || $section->sectionable_type !== Page::class) {
            return null;
        }

        return $section->sectionable_id;
    }

    private function pageOfItem(?int $sectionId): ?int
    {
        if ($sectionId === null) {
            return null;
        }

        return $this->pageOf(Section::query()->find($sectionId));
    }

    private function pageOfAttachment(MediaAttachment $attachment): ?int
    {
        return match ($attachment->attachable_type) {
            Section::class => $this->pageOf(Section::query()->find($attachment->attachable_id)),
            SectionItem::class => $this->pageOfItem(
                SectionItem::query()->find($attachment->attachable_id)?->section_id
            ),
            default => null,
        };
    }
}
