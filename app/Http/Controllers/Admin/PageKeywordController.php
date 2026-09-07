<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalysePageKeywords;
use App\Models\Page;
use App\Models\PageKeyword;
use App\Services\Seo\KeywordAnalyzer;
use App\Services\Seo\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Keywords aimed at one page, and how well that page serves each of them.
 *
 * The second SEO screen, beside the site-wide one rather than instead of it.
 * That one asks "does the site say this anywhere" and is where a list starts;
 * this one asks "is this page built around it", which is the question whose
 * answer is a list of edits.
 *
 * Three steps in the interface, in the order the work actually happens: pick
 * the page, pick the language, paste the words. Everything after that is
 * automatic — the score is written on entry and rewritten by
 * PageContentObserver whenever the page changes.
 */
class PageKeywordController extends Controller
{
    /** The same permission that governs the site-wide keyword screen. */
    private const PERMISSION = 'pages.view';

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $locales = array_keys(config('site.locales'));
        $locale = $request->string('locale')->toString();
        $locale = in_array($locale, $locales, true) ? $locale : $locales[0];

        $pages = $this->pages();
        $pageId = (int) $request->integer('page_id');

        // Defaults to the first page rather than to nothing: a screen that
        // opens empty and demands a choice before showing anything teaches
        // people it is broken.
        $pageId = collect($pages)->contains('id', $pageId)
            ? $pageId
            : (int) ($pages[0]['id'] ?? 0);

        return Inertia::render('Admin/Seo/PageKeywords', [
            'locales' => $locales,
            'locale' => $locale,
            'pages' => $pages,
            'pageId' => $pageId,
            'keywords' => $this->keywords($pageId, $locale),
            'weights' => KeywordAnalyzer::WEIGHTS,
            'bands' => [
                'weakBelow' => PageKeyword::WEAK_BELOW,
                'strongFrom' => PageKeyword::STRONG_FROM,
            ],
            'editUrl' => $pageId !== 0 ? "/admin/pages/{$pageId}/edit" : null,
        ]);
    }

    /**
     * Bulk entry: one box, any number of phrases, newline or comma separated.
     *
     * Copied from the site-wide screen deliberately — the same hands paste
     * the same list from the same spreadsheet, and two different entry
     * conventions in one panel is a thing to remember for no reason.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $data = $request->validate([
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('site.locales')))],
            'terms' => ['required', 'string', 'max:20000'],
        ]);

        $page = Page::query()->findOrFail($data['page_id']);
        $analyzer = app(KeywordAnalyzer::class);
        $normalizer = app(TextNormalizer::class);

        /*
         * Gathered once for the whole paste. Sixty phrases used to mean sixty
         * rebuilds of the same string — the page, every section, every card
         * and every alt text — for one substring search each.
         */
        $content = $analyzer->content($page, $data['locale']);

        $added = 0;
        $duplicates = 0;
        $seen = [];

        foreach ($this->split($data['terms']) as $term) {
            $normalized = $normalizer->normalise($term, $data['locale']);

            if ($normalized === '') {
                continue;
            }

            /*
             * Duplicates are dropped in silence, and counted.
             *
             * Refusing the whole paste because one of sixty phrases is
             * already there is the behaviour that makes people stop pasting.
             * `$seen` catches repeats inside the same paste, before the
             * database sees them.
             */
            $exists = in_array($normalized, $seen, true) || PageKeyword::query()
                ->where('page_id', $page->id)
                ->where('locale', $data['locale'])
                ->where('keyword_normalized', $normalized)
                ->exists();

            if ($exists) {
                $duplicates++;

                continue;
            }

            $seen[] = $normalized;

            $result = $analyzer->analyseAgainst($content, $data['locale'], $term);

            PageKeyword::query()->create([
                'page_id' => $page->id,
                'locale' => $data['locale'],
                'keyword' => Str::limit(trim($term), 180, ''),
                'keyword_normalized' => Str::limit($normalized, 180, ''),
                'score' => $result['score'],
                'checks' => $result['checks'] + [
                    'occurrences' => $result['occurrences'],
                    'density' => round($result['density'], 4),
                    'stuffed' => $result['stuffed'],
                ],
                'content_hash' => $result['content_hash'],
                'analyzed_at' => now(),
            ]);

            $added++;
        }

        return back()->with('success', __('settings.page_keywords.added', [
            'added' => $added,
            'duplicates' => $duplicates,
        ]));
    }

    public function destroy(Request $request, PageKeyword $pageKeyword): RedirectResponse
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $pageKeyword->delete();

        return back()->with('success', __('settings.page_keywords.removed'));
    }

    /**
     * Name the one phrase this page is actually about.
     *
     * A page can carry sixty keywords and have one subject. Marking it changes
     * no score and touches nothing the visitor sees — it is how the people
     * editing this page agree on what it is for, and it is the line every
     * other keyword on the list is judged against.
     */
    public function primary(Request $request, PageKeyword $pageKeyword): RedirectResponse
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $pageKeyword->makePrimary();

        return back()->with('success', __('settings.page_keywords.primary_set', [
            'keyword' => $pageKeyword->keyword,
        ]));
    }

    /**
     * Re-run every keyword on one page and language, on demand.
     *
     * Run here and now rather than queued, unlike the observer's automatic
     * pass. Somebody pressing "re-check everything" is standing in front of
     * the screen waiting for the numbers to move; handing them back the same
     * page with the same values and a cheerful message is indistinguishable
     * from the button being broken.
     *
     * Forced past the fingerprint for the same reason: the button exists for
     * the moment somebody doubts the numbers, and "nothing changed, so I did
     * nothing" is not an answer to a doubt.
     */
    public function reanalyse(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(self::PERMISSION), 403);

        $data = $request->validate([
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('site.locales')))],
        ]);

        AnalysePageKeywords::dispatchSync($data['page_id'], $data['locale'], force: true);

        return back()->with('success', __('settings.page_keywords.reanalysed'));
    }

    /**
     * Published pages, named as the editor knows them.
     *
     * @return list<array{id: int, title: string, slug: string}>
     */
    private function pages(): array
    {
        return Page::query()
            ->published()
            // Retired pages answer 301; choosing search terms for them would
            // be work spent on an address nobody reaches.
            ->notRetired()
            ->with('translations')
            ->get()
            ->map(fn (Page $page): array => [
                'id' => $page->id,
                // The Arabic title is the one on the sidebar and the one they
                // will recognise; the slug disambiguates two pages that share
                // a title.
                'title' => $page->translationFor('ar')?->title
                    ?? $page->translationFor('en')?->title
                    ?? $page->slug,
                'slug' => $page->slug,
            ])
            ->sortBy('title', SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function keywords(int $pageId, string $locale): array
    {
        if ($pageId === 0) {
            return [];
        }

        $rows = PageKeyword::query()
            ->where('page_id', $pageId)
            ->forLocale($locale)
            /*
             * The page's main keyword first, then strongest to weakest.
             *
             * This screen is read as "what is this page's standing", not as a
             * to-do list — so it opens with the phrase the page is for and the
             * ones it already earns, and the work to do is the tail. The
             * strongest-first order was asked for directly; the primary is
             * pinned above it because a subject that sorts into the middle of
             * its own list is not visibly the subject.
             */
            ->orderByDesc('is_primary')
            ->orderByDesc('score')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        /*
         * The page as it stands right now, against the content each score was
         * calculated from.
         *
         * The automatic re-analysis is queued, so between an editor saving a
         * page and a worker picking the job up, these rows describe the page
         * as it was. That window is normally a second and is occasionally
         * forever — nobody started a worker. Either way the screen says so
         * rather than presenting an old number as a current one, which is the
         * single thing that would make every other number here worthless.
         */
        $page = Page::query()->find($pageId);
        $current = $page !== null ? app(KeywordAnalyzer::class)->fingerprint($page, $locale) : null;

        return $rows
            ->map(fn (PageKeyword $row): array => [
                'id' => $row->id,
                'keyword' => $row->keyword,
                'score' => $row->score,
                'band' => $row->band(),
                'isPrimary' => $row->is_primary,
                'checks' => $row->checks ?? [],
                'stuffed' => (bool) ($row->checks['stuffed'] ?? false),
                'stale' => $current !== null && $row->content_hash !== $current,
                'analyzedAt' => $row->analyzed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * One paste into many phrases.
     *
     * @return list<string>
     */
    private function split(string $terms): array
    {
        // U+060C is the Arabic comma and is what an Arabic keyboard produces —
        // omitting it turned «دروع تكريم، تذكارات المؤتمرات» into one keyword
        // that matched nothing, silently, for every Arabic list pasted in.
        return collect(preg_split('/[\r\n,،؛;]+/u', $terms) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->values()
            ->all();
    }
}
