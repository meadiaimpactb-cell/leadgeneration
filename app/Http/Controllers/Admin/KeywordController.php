<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyword;
use App\Services\Seo\KeywordCoverage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The site's target keywords (§13).
 *
 * Entry is deliberately frictionless: paste a list, one phrase per line or
 * comma-separated, in whichever language, and they are all created. There is
 * no page to choose first — you learn what buyers search for before you know
 * which page should answer it, and a form that insists otherwise puts the work
 * in the wrong order.
 *
 * What the screen then does is the part that matters. It searches every
 * published page for each term and reports where it appears, because that —
 * not the keywords box — is what decides whether the site can rank for it.
 * A term matching no page is shown as a gap, which is the one output on this
 * screen an editor can actually act on.
 */
class KeywordController extends Controller
{
    public function index(Request $request, KeywordCoverage $coverage): Response
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $locales = array_keys(config('site.locales'));
        $report = [];

        foreach ($locales as $locale) {
            $report[$locale] = $coverage->report($locale);
        }

        return Inertia::render('Admin/Seo/Keywords', [
            'locales' => $locales,
            'report' => $report,
            'groups' => Keyword::query()
                ->whereNotNull('group')
                ->distinct()
                ->orderBy('group')
                ->pluck('group')
                ->all(),
        ]);
    }

    /**
     * Bulk entry. One textarea, any separator, unlimited terms.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:5'],
            'terms' => ['required', 'string', 'max:8000'],
            'group' => ['nullable', 'string', 'max:64'],
        ]);

        $terms = $this->parse($data['terms']);

        if ($terms === []) {
            return back()->with('error', __('settings.keywords.nothing_added'));
        }

        $added = 0;

        DB::transaction(function () use ($terms, $data, &$added): void {
            $next = (int) Keyword::query()->where('locale', $data['locale'])->max('sort_order');

            foreach ($terms as $term) {
                // firstOrCreate, so pasting an overlapping list is safe and an
                // editor never has to de-duplicate by hand.
                $keyword = Keyword::query()->firstOrCreate(
                    ['locale' => $data['locale'], 'term' => $term],
                    ['group' => $data['group'] ?? null, 'sort_order' => ++$next, 'is_active' => true],
                );

                if ($keyword->wasRecentlyCreated) {
                    $added++;
                }
            }
        });

        return back()->with('success', trans_choice('settings.keywords.added', $added, ['count' => $added]));
    }

    public function update(Request $request, Keyword $keyword): RedirectResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $data = $request->validate([
            'term' => ['required', 'string', 'max:191'],
            'group' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);

        $keyword->forceFill([
            'term' => $this->clean($data['term']),
            'group' => $data['group'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return back()->with('success', __('settings.saved'));
    }

    public function destroy(Request $request, Keyword $keyword): RedirectResponse
    {
        abort_unless($request->user()->can('pages.view'), 403);

        $keyword->delete();

        return back()->with('success', __('admin.deleted'));
    }

    /**
     * Splits a pasted block into terms.
     *
     * Newlines, commas and Arabic commas all separate, because the list will
     * arrive pasted out of a spreadsheet, an email or an SEO consultant's
     * document and nobody should have to reformat it first.
     *
     * @return list<string>
     */
    private function parse(string $raw): array
    {
        return collect(preg_split('/[\r\n,،;]+/u', $raw) ?: [])
            ->map(fn (string $term): string => $this->clean($term))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function clean(string $term): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', $term) ?? ''), 185, '');
    }
}
