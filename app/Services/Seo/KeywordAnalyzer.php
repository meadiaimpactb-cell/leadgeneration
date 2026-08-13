<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Media;
use App\Models\Page;
use App\Models\Section;
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
 * THE ARABIC NORMALISATION IS THE WHOLE THING
 *
 * Without it this tool is worse than useless: it reports words the page
 * plainly contains as missing, the editor sees red on text they are looking
 * at, and they stop believing any of it. «هدايا مؤسّسية» typed with a shadda
 * must match «هدايا مؤسسية» in the page, «إهداء» must match «اهداء», and
 * «هدية» must match «هديه». See normalise().
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

    /**
     * The full result for one keyword against one page.
     *
     * @return array{score: int, checks: array<string, array{passed: bool, weight: int}>, occurrences: int}
     */
    public function analyse(Page $page, string $locale, string $keyword): array
    {
        $needle = $this->normalise($keyword, $locale);
        $content = $this->content($page, $locale);

        if ($needle === '') {
            return ['score' => 0, 'checks' => $this->emptyChecks(), 'occurrences' => 0];
        }

        $occurrences = substr_count($content['body'], $needle);

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

        return ['score' => $score, 'checks' => $checks, 'occurrences' => $occurrences];
    }

    /**
     * The matching form of a string.
     *
     * Applied to the keyword and to the page's text alike — normalising only
     * one side is the same as not normalising at all.
     *
     * Arabic, in order:
     *
     *   · harakat and the dagger alef removed — a shadda in the page and none
     *     in the keyword is not a different word
     *   · أ إ آ ٱ → ا, and ؤ ئ → و ي, so hamza spelling stops mattering
     *   · ة → ه, because «هدية» and «هديه» are the same word to a reader and
     *     the site is not consistent about which it uses
     *   · ى → ي, same reason
     *   · tatweel removed, and Arabic-Indic digits folded to Latin so ٢٠٢٤
     *     matches 2024
     *
     * Both languages: lowercased, punctuation reduced to spaces so a phrase
     * followed by a comma still matches, and whitespace collapsed.
     */
    public function normalise(string $text, string $locale = 'ar'): string
    {
        $text = Str::lower(trim($text));

        // Harakat, tanween, shadda, sukun, dagger alef, and the tatweel.
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? $text;

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ؤ' => 'و', 'ئ' => 'ي', 'ة' => 'ه', 'ى' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        /*
         * Punctuation to spaces, not to nothing: «الحرف،» must match «الحرف»,
         * but «حرف-يدوي» must not silently become one word «حرفيدوي» that
         * matches neither half.
         */
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Everything the page says in this language, by field.
     *
     * Read from the database rather than by fetching the rendered page: the
     * markup is the same words plus a great deal of navigation, footer and
     * cookie copy that belongs to every page equally. Scoring a keyword
     * because it appears in the site footer would make every page look
     * equally good at everything.
     *
     * @return array{meta_title: string, heading: string, meta_description: string, body: string, first_paragraph: string, image_alt: string, slug: string}
     */
    public function content(Page $page, string $locale): array
    {
        $page->loadMissing([
            'translations',
            'sections' => fn ($q) => $q->where('is_active', true)
                ->with(['translations', 'media.translations', 'mediaAttachments.media.translations']),
        ]);

        $translation = $page->translationFor($locale);

        $body = [$translation?->subtitle, $translation?->excerpt];
        $alts = [];
        $firstParagraph = null;

        foreach ($page->sections->sortBy('sort_order') as $section) {
            $row = $section->translations->firstWhere('locale', $locale);

            foreach ([$row?->heading, $row?->subheading, $row?->body] as $field) {
                $body[] = $field;
            }

            // Cards, steps, questions — the copy inside a section's own JSON.
            $body[] = $this->prose($section->settings ?? [], $locale);

            $firstParagraph ??= $this->firstProse($row?->body ?? $row?->subheading);

            $alts[] = $this->altText($section, $locale);
        }

        return [
            'meta_title' => $this->normalise((string) $translation?->meta_title, $locale),
            // The H1 is the page's own title; meta_title is only what the tab
            // and the search result show, and they are edited separately.
            'heading' => $this->normalise((string) $translation?->title, $locale),
            'meta_description' => $this->normalise((string) $translation?->meta_description, $locale),
            'body' => $this->normalise($this->flatten($body), $locale),
            /*
             * What a visitor reads first, in the order they meet it: the line
             * under the page title, then the first section that says anything,
             * then the summary. Reading only the sections missed pages whose
             * opening sentence is the subtitle — which is most of them.
             */
            'first_paragraph' => $this->normalise(
                (string) ($translation?->subtitle ?: $firstParagraph ?: $translation?->excerpt),
                $locale,
            ),
            'image_alt' => $this->normalise($this->flatten($alts), $locale),
            'slug' => $this->normalise(str_replace(['-', '/'], ' ', (string) $page->slug), $locale),
        ];
    }

    /**
     * Prose inside a section's settings, in this locale only.
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
     * Both slots: the single image and the gallery, and both sources — the
     * library reference and an older direct upload — because `mediaFor()`'s
     * rule about which wins applies here too.
     */
    private function altText(Section $section, string $locale): string
    {
        $media = $section->mediaAttachments
            ->map(fn ($attachment) => $attachment->media)
            ->filter()
            ->merge($section->media)
            ->unique('id');

        return $media
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
        return implode(' ', array_filter(array_map(
            fn (?string $field): string => trim(html_entity_decode(
                strip_tags((string) $field), ENT_QUOTES | ENT_HTML5, 'UTF-8'
            )),
            $fields,
        )));
    }

    /**
     * Word-boundary-free containment, deliberately.
     *
     * Arabic prefixes attach to the word: «الهدايا» is «هدايا» with the
     * article, «وللجهات» is «جهات» with two. A boundary check would fail on
     * text a reader plainly sees as containing the phrase, which is the
     * failure mode that destroys trust in the whole screen.
     *
     * The cost is that a keyword which is a fragment of a longer word can
     * match it. For phrases — what people actually target — that is rare, and
     * the wrong direction to err in is the other one.
     */
    private function contains(string $haystack, string $needle): bool
    {
        return $haystack !== '' && str_contains($haystack, $needle);
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
