<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Page;
use App\Models\PageKeyword;
use App\Services\Seo\KeywordAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-scores a page's keywords after that page changes.
 *
 * This is what makes the screen feel automatic rather than like a report
 * somebody has to remember to run: an editor adds the phrase to their
 * headline, saves, returns to the keyword screen and the bar has already
 * moved. Nobody is ever told a score is stale, because it is not.
 *
 * QUEUED, AND WHY THE SCREEN STILL CANNOT LIE
 *
 * Saving a page must not wait on this. The cost of queueing is a window where
 * the stored score describes the page as it was a moment ago — and if no
 * worker is running, that window never closes. A stale colour that looks
 * exactly like a current one is the one failure this screen cannot survive.
 *
 * `content_hash` closes it from the other end: every row records the
 * fingerprint of the content it was scored against, so the screen can compare
 * it with the page as it stands now and say, in words, that a number is being
 * recalculated. The queue keeps saves fast; the fingerprint keeps the screen
 * honest whether or not the worker is up.
 *
 * The same fingerprint is what makes most of these jobs free: a save that
 * changed nothing the analyser reads leaves every hash equal and writes
 * nothing at all.
 *
 * Scoped to one locale when one locale changed, so editing the English copy
 * does not rewrite the Arabic scores with identical values and a new timestamp.
 */
class AnalysePageKeywords implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pageId,
        public readonly ?string $locale = null,
        /**
         * Ignore the fingerprint and re-score regardless.
         *
         * For the panel's "re-check everything" button, which exists precisely
         * for the moment somebody doubts the numbers. A button that quietly
         * did nothing because a hash matched would confirm the doubt.
         */
        public readonly bool $force = false,
    ) {}

    public function handle(KeywordAnalyzer $analyzer): void
    {
        $page = Page::query()->find($this->pageId);

        if ($page === null) {
            return;
        }

        $keywords = PageKeyword::query()
            ->where('page_id', $page->id)
            ->when($this->locale !== null, fn ($q) => $q->where('locale', $this->locale))
            ->get();

        // Gathered once per language, not once per keyword: the page, its
        // sections, their items and every alt text are the same string for all
        // sixty rows.
        $content = [];

        foreach ($keywords as $keyword) {
            $locale = $keyword->locale;
            $content[$locale] ??= $analyzer->content($page, $locale);

            if (! $this->force && $keyword->content_hash === $content[$locale]['hash']) {
                continue;
            }

            $result = $analyzer->analyseAgainst($content[$locale], $locale, $keyword->keyword);

            $keyword->forceFill([
                'score' => $result['score'],
                'checks' => $result['checks'] + [
                    'occurrences' => $result['occurrences'],
                    'density' => round($result['density'], 4),
                    'stuffed' => $result['stuffed'],
                ],
                'content_hash' => $result['content_hash'],
                'analyzed_at' => now(),
            ])->save();
        }
    }
}
