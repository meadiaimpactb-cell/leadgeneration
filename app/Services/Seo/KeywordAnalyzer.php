<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Media;
use App\Models\Page;
use App\Models\Section;
use App\Models\SectionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * How well one page serves one keyword, in one language.
 *
 * The companion to KeywordCoverage. That one answers "does the site say this
 * anywhere"; this one answers "is this page built around it", which is the
 * question with an actionable answer — every check below maps to one edit an
 * editor can make in the panel this afternoon.
 *
 * WHAT THIS IS NOT
 *
 * It is not a ranking prediction. Nothing here talks to a search engine, and
 * a green bar does not mean page one. It measures whether the page mentions
 * its own subject in the places a reader and a crawler both look first. That
 * is worth measuring precisely because it is so often skipped — a page about
 * government gift protocols whose title says «حلولنا» and nothing else.
 *
 * THE ARABIC FOLDING IS THE WHOLE THING
 *
 * See TextNormalizer. Without it this tool reports words the page plainly
 * contains as missing, and nothing on the screen is believed again.
 */
class KeywordAnalyzer
{
    /**
     * Every check, with the weight it contributes out of 100.
     *
     * The order is the order of the detail list in the panel, which is
     * roughly the order of effort: fixing the title is one field, spreading a
     * phrase naturally through body copy is an afternoon.
     *
     * `in_body_once` and `in_body_twice` are cumulative on purpose — a phrase
     * used twice scores both, for 25. The alternative, one band or the other,
     * makes the second mention worth more than the first, which reads as an
     * instruction to repeat rather than to write.
     */
    public const WEIGHTS = [
        'meta_title' => 25,
        'heading' => 20,
        'meta_description' => 15,
        'in_body_twice' => 15,
        'in_body_once' => 10,
        'first_paragraph' => 5,
        'image_alt' => 5,
        'slug' => 5,
    ];

    /**
     * Above this share of the page's words, the phrase is being stuffed.
     *
     * Warned about and never scored. A penalty here would mean the bar drops
     * while the editor is doing the thing the bar just asked for — mentioning
     * the phrase — and no explanation survives that. Three percent is the
     * conventional line; the point is not the exact number but that somebody
     * repeating a phrase forty times is told, in words, to stop.
     */
    public const STUFFING_DENSITY = 0.03;

    /**
     * No phrase used this few times is being stuffed, whatever the density
     * says. See isStuffing() — the density line alone flagged a single
     * mention in a short excerpt.
     */
    public const STUFFING_MIN_REPEATS = 3;

    /** Below this, a share of the text is not a meaningful measurement. */
    public const STUFFING_MIN_WORDS = 60;

    /**
     * Settings keys that hold a machine value, not prose.
     *
     * A URL containing "government" is not the page discussing government,
     * and counting it would score a page for a word no reader ever sees.
     */
    private const NON_PROSE_KEYS = [
        'url', 'href', 'link', 'image', 'images', 'poster', 'icon', 'id', 'ids',
        'key', 'keys', 'group', 'slug', 'variant', 'layout', 'style', 'colour',
        'color', 'flip', 'align', 'columns', 'anchor', 'file',
    ];

    public function __construct(private readonly TextNormalizer $normalizer) {}

    /**
     * Everything content() needs, in one place.
     *
     * Shared with the job so the two cannot drift: a relation missing from one
     * of them is a lazy load, and `preventLazyLoading` turns that into an
     * exception mid-save in development and a query per row in production.
     *
     * @return array<string, mixed>
     */
    public static function eagerLoads(): array
    {
        return [
            'translations',
            'sections' => fn ($q) => $q->where('is_active', true)
                ->with([
                    'translations',
                    'media.translations',
                    'mediaAttachments.media.translations',
                    // Only live items: an item switched off is not on the page,
                    // and scoring the page for words nobody can read is the
                    // same lie as scoring it for its own footer.
                    'items' => fn ($items) => $items->where('is_active', true)
                        ->with(['translations', 'media.translations']),
                ]),
        ];
    }

    /**
     * The full result for one keyword against one page.
     *
     * @return array{score: int, checks: array<string, array{passed: bool, weight: int}>, occurrences: int, density: float, stuffed: bool, content_hash: string}
     */
    public function analyse(Page $page, string $locale, string $keyword): array
    {
        return $this->analyseAgainst($this->content($page, $locale), $locale, $keyword);
    }

    /**
     * The same, against content already gathered.
     *
     * The entry point every batch uses. Reading a page, its translations, its
     * sections, their translations, their items and every alt text costs the
     * same whether one keyword or sixty are being scored against it — so the
     * caller gathers once and scores many. Sixty keywords used to mean sixty
     * rebuilds of the identical string.
     *
     * @param  array<string, mixed>  $content  from content()
     * @return array{score: int, checks: array<string, array{passed: bool, weight: int}>, occurrences: int, density: float, stuffed: bool, content_hash: string}
     */
    public function analyseAgainst(array $content, string $locale, string $keyword): array
    {
        $needle = $this->normalizer->normalise($keyword, $locale);

        if ($needle === '') {
            return [
                'score' => 0,
                'checks' => $this->emptyChecks(),
                'occurrences' => 0,
                'density' => 0.0,
                'stuffed' => false,
                'content_hash' => (string) $content['hash'],
            ];
        }

        $occurrences = substr_count((string) $content['body'], $needle);

        $results = [
            'meta_title' => $this->contains($content['meta_title'], $needle),
            'heading' => $this->contains($content['heading'], $needle),
            'meta_description' => $this->contains($content['meta_description'], $needle),
            'in_body_twice' => $occurrences >= 2,
            'in_body_once' => $occurrences >= 1,
            'first_paragraph' => $this->contains($content['first_paragraph'], $needle),
            'image_alt' => $this->contains($content['image_alt'], $needle),
            /*
             * Arabic slugs are transliterated or English by convention here,
             * so an Arabic phrase can never appear in one. Scoring it as a
             * failure would cap every Arabic keyword at 95 for something the
             * editor cannot fix — a permanent red mark that teaches people to
             * ignore the list. It is awarded and the panel says why.
             */
            'slug' => $locale === 'en' ? $this->contains($content['slug'], $needle) : true,
        ];

        $checks = [];
        $score = 0;

        foreach (self::WEIGHTS as $name => $weight) {
            $passed = $results[$name];
            $checks[$name] = ['passed' => $passed, 'weight' => $weight];
            $score += $passed ? $weight : 0;
        }

        $density = $this->density($needle, $occurrences, (int) $content['words']);

        return [
            'score' => $score,
            'checks' => $checks,
            'occurrences' => $occurrences,
            'density' => $density,
            'stuffed' => $this->isStuffing($occurrences, (int) $content['words'], $density),
            'content_hash' => (string) $content['hash'],
        ];
    }

    /**
     * A fingerprint of everything a score depends on.
     *
     * Two uses, both in page_keywords.content_hash: skipping a re-analysis
     * that cannot change any answer, and telling the screen that a stored
     * score describes a page that has since been edited.
     */
    public function fingerprint(Page $page, string $locale): string
    {
        return (string) $this->content($page, $locale)['hash'];
    }

    /**
     * What the page says in this language, by field, already normalised.
     *
     * Read from the database rather than by fetching the rendered page: the
     * markup is the same words plus a great deal of navigation, footer and
     * cookie copy that belongs to every page equally. Scoring a keyword
     * because it appears in the site footer would make every page look
     * equally good at everything.
     *
     * @return array{meta_title: string, heading: string, meta_description: string, body: string, first_paragraph: string, image_alt: string, slug: string, words: int, hash: string}
     */
    public function content(Page $page, string $locale): array
    {
        /*
         * `load`, not `loadMissing`: a caller that already eager-loaded
         * `sections` without `items` would keep its shallower version, and the
         * card copy would silently vanish from the body — the exact failure
         * this method exists to prevent.
         */
        $page->load(self::eagerLoads());

        $translation = $page->translationFor($locale);

        $body = [$translation?->subtitle, $translation?->excerpt];
        $alts = [];
        $heroHeading = null;
        $openingProse = null;

        foreach ($page->sections->sortBy('sort_order') as $section) {
            $row = $section->translations->firstWhere('locale', $locale);

            foreach ([$row?->heading, $row?->subheading, $row?->body] as $field) {
                $body[] = $field;
            }

            /*
             * Repeatable copy — cards, steps, questions, stations — from both
             * of its homes.
             *
             * `section_items` is the table it is moving to; `settings->items[]`
             * is where all of it still lives until that migration has run and
             * been verified (see docs/dynamic-audit.md). Reading only one of
             * them would mean the analyser is wrong either today or on the day
             * of the changeover, and the failure would be silent both times:
             * a page whose three cards carry the phrase, scored as never
             * mentioning it.
             */
            $body[] = $this->prose($section->settings ?? [], $locale);

            foreach ($section->items as $item) {
                $body[] = $this->itemProse($item, $locale);
                $alts[] = $this->altsOf($item->media, $locale);
            }

            // The visible H1 is usually the page title, but a page whose hero
            // carries the real headline is just as much a match — §13 cares
            // about the first heading a reader meets, not which table it is in.
            if ($section->type === 'hero') {
                $heroHeading ??= $row?->heading;
            }

            $openingProse ??= $this->firstProse($row?->body ?? $row?->subheading);

            $alts[] = $this->altText($section, $locale);
        }

        $bodyText = $this->normalise($this->flatten($body), $locale);

        $fields = [
            'meta_title' => $this->normalise((string) $translation?->meta_title, $locale),
            // The page's own title, plus the hero headline when it has one:
            // either is the heading a reader sees first, and matching either
            // is the thing being measured.
            'heading' => $this->normalise($this->flatten([$translation?->title, $heroHeading]), $locale),
            'meta_description' => $this->normalise((string) $translation?->meta_description, $locale),
            'body' => $bodyText,
            /*
             * What a visitor reads first, in the order they meet it: the line
             * under the page title and the first section that says anything.
             * Reading only the sections missed pages whose opening sentence is
             * the subtitle — which is most of them.
             */
            'first_paragraph' => $this->normalise(
                $this->flatten([$translation?->subtitle, $openingProse])
                    ?: (string) $translation?->excerpt,
                $locale,
            ),
            'image_alt' => $this->normalise($this->flatten($alts), $locale),
            'slug' => $this->normalise(str_replace(['-', '/'], ' ', (string) $page->slug), $locale),
        ];

        return $fields + [
            'words' => $this->normalizer->wordCount($bodyText),
            // Every field a check reads, so the fingerprint changes when and
            // only when some answer could have changed.
            'hash' => sha1(implode("\n", $fields)),
        ];
    }

    /**
     * What share of the page's words this phrase is.
     *
     * Counted in words on both sides: three occurrences of a three-word phrase
     * account for nine of the page's words, not three. Measuring occurrences
     * against words would call a long phrase sparse when it dominates the copy.
     */
    /**
     * Whether repetition has crossed from emphasis into stuffing.
     *
     * Density alone cannot answer this, and using it alone was wrong: one
     * mention of a two-word phrase in a thirty-one-word excerpt is 6% — twice
     * the limit, and completely ordinary writing. The screen would have told
     * an editor to stop doing the thing it had just asked them to do.
     *
     * So two gates come first, and both are about what the word means rather
     * than about arithmetic:
     *
     *   · stuffing IS repetition. A phrase used three times or fewer is not
     *     being stuffed however short the page is.
     *   · a percentage of a very short text is noise. Under a paragraph or
     *     two there is no share to speak of.
     *
     * Only past both does the density line apply.
     */
    private function isStuffing(int $occurrences, int $bodyWords, float $density): bool
    {
        if ($occurrences <= self::STUFFING_MIN_REPEATS || $bodyWords < self::STUFFING_MIN_WORDS) {
            return false;
        }

        return $density > self::STUFFING_DENSITY;
    }

    private function density(string $needle, int $occurrences, int $bodyWords): float
    {
        if ($occurrences === 0 || $bodyWords === 0) {
            return 0.0;
        }

        return ($occurrences * $this->normalizer->wordCount($needle)) / $bodyWords;
    }

    /**
     * One repeatable item's copy, in this locale.
     *
     * `cta_url` is skipped for the same reason a settings URL is: an address
     * containing the phrase is not the page saying it.
     */
    private function itemProse(SectionItem $item, string $locale): string
    {
        $row = $item->translations->firstWhere('locale', $locale);

        return $this->flatten([
            $row?->title,
            $row?->body,
            $row?->cta_label,
            $this->prose($item->settings ?? [], $locale),
        ]);
    }

    /**
     * Prose inside a settings array, in this locale only.
     *
     * The project's JSON convention is a plain key for Arabic and the same
     * key suffixed `_en` for English — `title` / `title_en`. Taking every
     * string would score an Arabic keyword against English card copy sitting
     * in the same array, and score the page for words its Arabic readers
     * never see.
     *
     * @param  array<array-key, mixed>  $settings
     */
    private function prose(array $settings, string $locale): string
    {
        $out = [];

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $out[] = $this->prose($value, $locale);

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $name = is_string($key) ? Str::lower($key) : '';
            $english = str_ends_with($name, '_en');
            $base = $english ? substr($name, 0, -3) : $name;

            if (in_array($base, self::NON_PROSE_KEYS, true)) {
                continue;
            }

            // A path or an address is never prose, whatever its key is called.
            if (Str::startsWith($value, ['/', 'http://', 'https://', '#', 'mailto:', 'tel:'])) {
                continue;
            }

            if ($locale === 'en' ? $english : ! $english) {
                $out[] = $value;
            }
        }

        return implode(' ', array_filter($out));
    }

    /**
     * The alt text of every image the section shows, in this locale.
     *
     * Both slots — the single image and the gallery — and both sources, the
     * library reference and an older direct upload, because `mediaFor()`'s
     * rule about which one wins applies here too.
     */
    private function altText(Section $section, string $locale): string
    {
        $media = $section->mediaAttachments
            ->map(fn ($attachment) => $attachment->media)
            ->filter()
            ->merge($section->media)
            ->unique('id');

        return $this->altsOf($media, $locale);
    }

    /**
     * @param  Collection<int, Media>|\Illuminate\Database\Eloquent\Collection<int, Media>  $media
     */
    private function altsOf($media, string $locale): string
    {
        return collect($media)
            ->map(fn (Media $item): ?string => $item->translations->firstWhere('locale', $locale)?->alt_text)
            ->filter()
            ->implode(' ');
    }

    /**
     * The first real sentence of a rich-text field, tags stripped.
     *
     * "First paragraph" is a proxy for "above the fold and read first". An
     * empty <p> or a lone image at the top of the body should not consume it.
     */
    private function firstProse(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        foreach (preg_split('/<\/p>|<br\s*\/?>|\n{2,}/i', $html) ?: [] as $chunk) {
            $text = trim(html_entity_decode(strip_tags($chunk), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /** @param  list<string|null>  $fields */
    private function flatten(array $fields): string
    {
        return trim(implode(' ', array_filter(array_map(
            fn (?string $field): string => trim(html_entity_decode(
                strip_tags((string) $field), ENT_QUOTES | ENT_HTML5, 'UTF-8'
            )),
            $fields,
        ))));
    }

    private function normalise(string $text, string $locale): string
    {
        return $this->normalizer->normalise($text, $locale);
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $this->normalizer->contains($haystack, $needle);
    }

    /** @return array<string, array{passed: bool, weight: int}> */
    private function emptyChecks(): array
    {
        return array_map(
            fn (int $weight): array => ['passed' => false, 'weight' => $weight],
            self::WEIGHTS,
        );
    }
}
