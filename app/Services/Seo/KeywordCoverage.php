<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Keyword;
use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Answers the only question a keyword list can usefully answer: which of these
 * words does the site actually say anywhere?
 *
 * This exists because the obvious feature is a lie. Typing a phrase into a
 * keywords box does not make Google index it — the meta tag has been ignored
 * since 2009, and search engines rank pages for words those pages contain.
 * Shipping the box alone would let the client believe they had done SEO.
 *
 * So the box stays — entry has to be effortless — and this turns it into a
 * report. For every term it searches the published pages' titles, subtitles,
 * descriptions and section copy, and says where the term appears. A term that
 * appears nowhere is a content gap: the honest, actionable output, and the
 * thing that actually decides whether the site can rank for it.
 */
class KeywordCoverage
{
    /**
     * @return list<array{
     *     id: int, term: string, group: string|null, locale: string,
     *     pages: list<array{id: int, slug: string, title: string|null, inTitle: bool}>,
     *     covered: bool, strong: bool
     * }>
     */
    public function report(string $locale): array
    {
        $keywords = Keyword::query()->visible()->forLocale($locale)->get();

        if ($keywords->isEmpty()) {
            return [];
        }

        $pages = $this->searchablePages($locale);

        return $keywords->map(function (Keyword $keyword) use ($pages): array {
            $needle = Str::lower($keyword->term);
            $matches = [];

            foreach ($pages as $page) {
                $inTitle = Str::contains($page['title_haystack'], $needle);
                $inBody = Str::contains($page['body_haystack'], $needle);

                if ($inTitle || $inBody) {
                    $matches[] = [
                        'id' => $page['id'],
                        'slug' => $page['slug'],
                        'title' => $page['title'],
                        'inTitle' => $inTitle,
                    ];
                }
            }

            return [
                'id' => $keyword->id,
                'term' => $keyword->term,
                'group' => $keyword->group,
                'locale' => $keyword->locale,
                'pages' => $matches,
                'covered' => $matches !== [],
                // "Strong" means at least one page carries it in the title or
                // description — the fields search engines weigh most heavily.
                // Buried in body copy counts, but not for as much.
                'strong' => collect($matches)->contains('inTitle', true),
            ];
        })->all();
    }

    /**
     * Published, indexable pages flattened into two haystacks each: the
     * headline fields, and everything else the page renders.
     *
     * @return list<array{id: int, slug: string, title: string|null, title_haystack: string, body_haystack: string}>
     */
    private function searchablePages(string $locale): array
    {
        return Page::query()
            ->published()
            ->where('is_indexable', true)
            ->with([
                'translations',
                'sections' => fn ($q) => $q->where('is_active', true)->with('translations'),
            ])
            ->get()
            ->filter(fn (Page $page): bool => $page->hasTranslation($locale))
            ->map(function (Page $page) use ($locale): array {
                $translation = $page->translationFor($locale);

                $titleFields = [
                    $translation?->title,
                    $translation?->meta_title,
                    $translation?->meta_description,
                ];

                $bodyFields = [$translation?->subtitle, $translation?->excerpt];

                foreach ($page->sections as $section) {
                    $row = $section->translations->firstWhere('locale', $locale);

                    $bodyFields[] = $row?->heading;
                    $bodyFields[] = $row?->subheading;
                    $bodyFields[] = $row?->body;
                }

                return [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $translation?->title,
                    'title_haystack' => $this->haystack($titleFields),
                    'body_haystack' => $this->haystack($bodyFields),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string|null>  $fields
     */
    private function haystack(array $fields): string
    {
        return Str::lower(preg_replace('/\s+/u', ' ', implode(' ', array_filter($fields))) ?? '');
    }
}
