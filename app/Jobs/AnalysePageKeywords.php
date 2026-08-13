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
 * This is what makes the screen feel automatic rather than like a report you
 * have to remember to run: an editor adds the phrase to their headline, saves,
 * goes back to the keyword screen and the bar is already green. Nobody has to
 * be told that a score is stale, because it never is.
 *
 * Queued because it is triggered by saving a page, and a save must not wait
 * on it. `QUEUE_CONNECTION=sync` — which is what the tests and a machine with
 * no worker use — runs it inline, so the behaviour is identical either way,
 * only slower.
 *
 * Scoped to one locale when one locale changed, so editing the English copy
 * does not rewrite the Arabic scores with identical values and a new
 * timestamp.
 */
class AnalysePageKeywords implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pageId,
        public readonly ?string $locale = null,
    ) {}

    public function handle(KeywordAnalyzer $analyzer): void
    {
        $page = Page::query()
            ->with([
                'translations',
                'sections' => fn ($q) => $q->where('is_active', true)
                    ->with(['translations', 'media.translations', 'mediaAttachments.media.translations']),
            ])
            ->find($this->pageId);

        if ($page === null) {
            return;
        }

        $keywords = PageKeyword::query()
            ->where('page_id', $page->id)
            ->when($this->locale !== null, fn ($q) => $q->where('locale', $this->locale))
            ->get();

        foreach ($keywords as $keyword) {
            $result = $analyzer->analyse($page, $keyword->locale, $keyword->keyword);

            $keyword->forceFill([
                'score' => $result['score'],
                'checks' => $result['checks'] + ['occurrences' => $result['occurrences']],
                'analyzed_at' => now(),
            ])->save();
        }
    }
}
