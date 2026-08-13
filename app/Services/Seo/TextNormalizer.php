<?php

declare(strict_types=1);

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * The matching form of a string — the one place Arabic is folded.
 *
 * WHY THIS IS ITS OWN CLASS
 *
 * Every comparison in the per-page analyser runs through here, and it must run
 * on both sides of that comparison: normalising the keyword but not the page
 * text is the same as not normalising at all. Keeping the rules in one object
 * is what makes "both sides" checkable rather than a thing to remember.
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS
 *
 * Without folding, the tool reports words the editor is looking at as missing.
 * «هدايا مؤسّسية» typed with a shadda must match «هدايا مؤسسية» in the page,
 * «إهداء» must match «اهداء», «هدية» must match «هديه». The first time an
 * editor sees red on text they can plainly read, nothing on the screen is
 * believed again — so this is the feature, not a detail of it.
 *
 * NOT USED BY KeywordCoverage, DELIBERATELY
 *
 * The site-wide screen matches with `Str::lower` and nothing else. Pointing it
 * here would change numbers an editor has already read and acted on, in a
 * screen this work was told not to touch. The upgrade is worth making as its
 * own decision, with its own before/after — see docs/seo-per-page-module.md.
 */
class TextNormalizer
{
    /**
     * Fold a string to its matching form.
     *
     * Arabic, in order:
     *
     *   · harakat, tanween, shadda, sukun and the dagger alef removed — a
     *     shadda in the page and none in the keyword is not a different word
     *   · أ إ آ ٱ → ا, ؤ → و, ئ → ي, so hamza spelling stops mattering
     *   · ة → ه, because «هدية» and «هديه» are one word to a reader and the
     *     site is not consistent about which it writes
     *   · ى → ي, same reason
     *   · tatweel removed, Arabic-Indic digits folded so ٢٠٣٠ matches 2030
     *
     * Both languages: lowercased, punctuation reduced to spaces, whitespace
     * collapsed.
     *
     * `$locale` is accepted because callers always know it and a future rule
     * may need it; today the folding is language-agnostic by design — an
     * Arabic phrase inside English copy should still match.
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
     * Containment, without word boundaries — deliberately.
     *
     * Arabic prefixes attach to the word: «الهدايا» is «هدايا» with the
     * article, «وللجهات» is «جهات» with two. A boundary check would fail on
     * text a reader plainly sees as containing the phrase, which is exactly
     * the failure that destroys trust in the whole screen.
     *
     * The cost is that a keyword which is a fragment of a longer word matches
     * it. For the phrases people actually target that is rare, and it is the
     * cheaper of the two errors to make.
     *
     * Both arguments must already be normalised.
     */
    public function contains(string $haystack, string $needle): bool
    {
        return $haystack !== '' && $needle !== '' && str_contains($haystack, $needle);
    }

    /**
     * How many words a normalised string holds.
     *
     * Used for keyword density, where the unit has to be the same on both
     * sides of the ratio: occurrences of a three-word phrase account for three
     * words of the page, not one.
     */
    public function wordCount(string $normalised): int
    {
        if ($normalised === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
