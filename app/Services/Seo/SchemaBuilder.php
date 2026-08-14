<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Section;
use App\Models\Solution;
use App\Models\Story;
use App\Support\Brand;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\SchemaOrg\Article;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\FAQPage;
use Spatie\SchemaOrg\LocalBusiness;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\Service;
use Spatie\SchemaOrg\WebSite;

/**
 * Structured data for search engines (§13).
 *
 * WHAT THIS IS FOR
 *
 * A crawler reads a page as text and guesses what the business is. Schema.org
 * stops the guessing: it states, in a format Google parses exactly, that this
 * is a company in Riyadh with this phone number, that this page answers these
 * questions, that the visitor is three levels deep in this section. It is the
 * difference between a plain blue link and a result carrying a phone number,
 * a breadcrumb trail, or an expandable list of questions.
 *
 * THE ONE RULE HERE
 *
 * Every value comes from the database or `settings`. Nothing is written in
 * this file (§22.1). A field with no value is OMITTED rather than filled with
 * a plausible default — structured data is a set of claims made to Google in
 * the client's name, and a wrong claim is worse than a missing one. Google
 * penalises structured data that disagrees with the visible page.
 *
 * WHY NOT `spatie/laravel-seo` OR SIMILAR
 *
 * `spatie/schema-org` builds JSON and nothing else: no routes, no config, no
 * opinion about meta tags. MetaBuilder and SitemapGenerator keep their jobs
 * untouched. A package that also managed titles would have created a second
 * source of truth for values the panel already owns.
 */
class SchemaBuilder
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Brand $brand,
        private readonly Request $request,
    ) {}

    /**
     * The whole graph for one page, as a JSON string ready for a
     * <script type="application/ld+json"> tag.
     *
     * Returned as one `@graph` rather than several separate script tags so the
     * nodes can reference each other by `@id` — the FAQ and the breadcrumbs
     * both point back at the same organisation record instead of repeating it.
     *
     * @param  array{breadcrumbs?: list<array{label: string|null, url: string|null}>, owner?: Model|null}  $context
     */
    public function build(array $context = []): ?string
    {
        $nodes = array_filter([
            $this->organisation(),
            $this->website(),
            $this->breadcrumbs($context['breadcrumbs'] ?? []),
            $this->faq($context['owner'] ?? null),
            $this->service($context['owner'] ?? null),
            $this->article($context['owner'] ?? null),
        ]);

        if ($nodes === []) {
            return null;
        }

        $graph = [];

        foreach ($nodes as $node) {
            $array = $node->toArray();

            // `@context` belongs to the document, not to each node inside it.
            unset($array['@context']);

            $graph[] = $array;
        }

        /*
         * `JSON_HEX_TAG` escapes `<` to `<`, which is what stops a stray
         * `</script>` in any database value from closing the tag this JSON is
         * printed inside. Unicode and slashes stay readable — Arabic names and
         * URLs are meant to be legible to whoever inspects the page.
         */
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG,
        ) ?: null;
    }

    /**
     * The company itself.
     *
     * A LocalBusiness when there is a real address to give, an Organization
     * otherwise. LocalBusiness is the more specific type and the one that can
     * earn a map result — but claiming it without an address is claiming a
     * shopfront that Google cannot verify.
     */
    private function organisation(): Organization|LocalBusiness|null
    {
        $locale = app()->getLocale();

        $name = $this->settings->get("site.name.{$locale}")
            ?? $this->settings->get('site.name');

        // With no name there is nothing to identify, and an anonymous
        // Organization node is noise.
        if (blank($name)) {
            return null;
        }

        $address = $this->settings->get("contact.address.{$locale}");
        $node = filled($address) ? Schema::localBusiness() : Schema::organization();

        $node->setProperty('@id', $this->id('organization'))
            ->name($name)
            ->url(url('/'));

        if (filled($address)) {
            /*
             * The address is one free-text box in the panel, three lines of
             * it. Splitting that into streetAddress / addressLocality /
             * postalCode would mean guessing which Arabic line is the
             * district and which the city — and a wrong locality is a wrong
             * claim about where the company is. The whole text goes in as the
             * street address, which is true, and the rest is left out.
             */
            $node->address(
                Schema::postalAddress()->streetAddress($this->oneLine($address))
            );
        }

        foreach ([
            'telephone' => 'contact.phone',
            'email' => 'contact.email',
        ] as $property => $key) {
            $value = $this->settings->get($key);

            if (filled($value)) {
                $node->setProperty($property, $value);
            }
        }

        // The logo is a raster file, not the inline SVG the site draws with —
        // Google's knowledge panel needs a fetchable image. Omitted until one
        // is uploaded on the brand screen.
        $logo = $this->absolute($this->brand->url('logo_light') ?? $this->brand->url('logo_dark'));

        if ($logo !== null) {
            $node->logo($logo)->image($logo);
        }

        $sameAs = $this->socialProfiles();

        if ($sameAs !== []) {
            $node->sameAs($sameAs);
        }

        $map = $this->settings->get('contact.map_url');

        if (filled($map)) {
            $node->setProperty('hasMap', $map);
        }

        /*
         * Opening hours are deliberately absent. The panel stores them as a
         * sentence — «الأحد – الخميس · 9:00 ص – 5:00 م» — and schema.org wants
         * "Su-Th 09:00-17:00". Parsing that sentence would be guesswork, and a
         * business shown as open when it is closed is a worse outcome than one
         * whose hours Google does not know. Structuring the field is its own
         * decision; see docs/seo-audit.md.
         */

        return $node;
    }

    /** The site as a thing, so the organisation is named as its publisher. */
    private function website(): WebSite
    {
        $locale = app()->getLocale();

        $name = $this->settings->get("site.name.{$locale}")
            ?? $this->settings->get('site.name')
            ?? config('app.name');

        return Schema::webSite()
            ->setProperty('@id', $this->id('website'))
            ->url(url('/'))
            ->name($name)
            ->inLanguage($locale)
            ->publisher(Schema::organization()->setProperty('@id', $this->id('organization')));
    }

    /**
     * The trail the visitor is standing in.
     *
     * Built from the same array the page renders as visible links, so the two
     * cannot disagree — Google checks that they match.
     *
     * @param  list<array{label: string|null, url: string|null}>  $trail
     */
    private function breadcrumbs(array $trail): ?BreadcrumbList
    {
        $trail = array_values(array_filter($trail, fn (array $c): bool => filled($c['label'])));

        // A single crumb is the page you are already on. There is no trail to
        // describe, and Google ignores one-item lists anyway.
        if (count($trail) < 2) {
            return null;
        }

        $items = [];

        foreach ($trail as $position => $crumb) {
            $item = Schema::listItem()
                ->position($position + 1)
                ->name($crumb['label']);

            // The last crumb is the current page and carries no link, which is
            // exactly how Google expects a trail to end.
            if (filled($crumb['url'])) {
                $item->item($this->absolute($crumb['url']));
            }

            $items[] = $item;
        }

        return Schema::breadcrumbList()->itemListElement($items);
    }

    /**
     * The questions this page answers, if it answers any.
     *
     * Read from the page's own accordion sections rather than from a separate
     * field somebody would have to maintain twice. An FAQ block on the page is
     * the FAQ — if the editor adds a question, the structured data gains it on
     * the next request with nothing else to remember.
     *
     * Takes any model that owns sections, not just a Page: the accordions that
     * exist today sit on Solutions and Sectors, and those are exactly the
     * pages a government buyer lands on with a question.
     */
    private function faq(?Model $owner): ?FAQPage
    {
        if ($owner === null || ! method_exists($owner, 'sections')) {
            return null;
        }

        $locale = app()->getLocale();
        $questions = [];

        $sections = $owner->relationLoaded('sections')
            ? $owner->sections
            : $owner->sections()->where('is_active', true)->get();

        foreach ($sections as $section) {
            if ($section->type !== 'accordion' || ! $section->is_active) {
                continue;
            }

            foreach ($this->accordionItems($section, $locale) as [$question, $answer]) {
                if (blank($question) || blank($answer)) {
                    continue;
                }

                $questions[] = Schema::question()
                    ->name($question)
                    ->acceptedAnswer(Schema::answer()->text($answer));
            }
        }

        if ($questions === []) {
            return null;
        }

        return Schema::fAQPage()->mainEntity($questions);
    }

    /**
     * What the company offers, on the page that offers it (§13).
     *
     * Only for Solutions. The four segment pages address an audience — a
     * government buyer, a partner — and an audience is not a service; typing
     * them as one would tell Google the company sells «الجهات الحكومية».
     */
    private function service(?Model $owner): ?Service
    {
        if (! $owner instanceof Solution) {
            return null;
        }

        $name = $this->plain($owner->t('name'));

        if (blank($name)) {
            return null;
        }

        $node = Schema::service()
            ->name($name)
            ->provider(Schema::organization()->setProperty('@id', $this->id('organization')))
            ->url($this->request->url());

        $description = $this->plain($owner->t('summary'));

        if (filled($description)) {
            $node->description($description);
        }

        $image = $owner->getFirstMediaUrl('hero');

        if (filled($image)) {
            $node->image($this->absolute($image));
        }

        return $node;
    }

    /**
     * An artisan's story as an article (§13).
     *
     * `datePublished` comes from the record's own timestamp rather than a
     * field somebody maintains: a wrong date is a claim Google checks against
     * the page, and the row already knows when it appeared.
     */
    private function article(?Model $owner): ?Article
    {
        if (! $owner instanceof Story) {
            return null;
        }

        $headline = $this->plain($owner->t('title'));

        if (blank($headline)) {
            return null;
        }

        $node = Schema::article()
            ->headline($headline)
            ->mainEntityOfPage($this->request->url())
            ->publisher(Schema::organization()->setProperty('@id', $this->id('organization')))
            ->inLanguage(app()->getLocale());

        // The pull quote is the story in the artisan's own words, and is what
        // the page itself uses as the summary.
        $quote = $this->plain($owner->t('quote'));

        if (filled($quote)) {
            $node->description($quote);
        }

        $image = $owner->getFirstMediaUrl('portrait') ?: $owner->getFirstMediaUrl('image');

        if (filled($image)) {
            $node->image($this->absolute($image));
        }

        if ($owner->created_at !== null) {
            $node->datePublished($owner->created_at->toAtomString());
        }

        if ($owner->updated_at !== null) {
            $node->dateModified($owner->updated_at->toAtomString());
        }

        return $node;
    }

    /**
     * One accordion's question/answer pairs, from both places they live.
     *
     * `section_items` is the table this content is moving to; `settings->items[]`
     * is where it still lives until that migration runs. Reading one only would
     * make the FAQ markup silently empty on one side of the changeover.
     *
     * @return list<array{0: string|null, 1: string|null}>
     */
    private function accordionItems(Section $section, string $locale): array
    {
        $pairs = [];

        $items = $section->relationLoaded('items')
            ? $section->items
            : $section->items()->where('is_active', true)->get();

        foreach ($items as $item) {
            $row = $item->translationFor($locale);

            $pairs[] = [$this->plain($row?->title), $this->plain($row?->body)];
        }

        /*
         * The JSON form: a plain key for Arabic, the same key with `_en` for
         * English — the project's convention everywhere `settings` holds copy.
         *
         * Two key names, because the seeders wrote accordions as
         * `question`/`answer` and everything else as `title`/`body`. Reading
         * only the generic pair found nothing on all nine accordions on this
         * site, and did so silently — an FAQPage node simply never appeared.
         */
        $suffix = $locale === 'en' ? '_en' : '';

        foreach ((array) $section->setting('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $item['question'.$suffix] ?? $item['title'.$suffix] ?? null;
            $answer = $item['answer'.$suffix] ?? $item['body'.$suffix] ?? null;

            $pairs[] = [$this->plain($question), $this->plain($answer)];
        }

        return $pairs;
    }

    /**
     * Profile URLs for `sameAs`, which is how Google ties the social accounts
     * to the company rather than treating them as unrelated pages.
     *
     * @return list<string>
     */
    private function socialProfiles(): array
    {
        $social = $this->settings->get('contact.social');

        if (! is_array($social)) {
            return [];
        }

        return collect($social)
            ->pluck('url')
            ->filter(fn ($url): bool => is_string($url) && Str::startsWith($url, ['http://', 'https://']))
            ->values()
            ->all();
    }

    /** A stable identifier so nodes can reference each other within the graph. */
    private function id(string $fragment): string
    {
        return url('/').'#'.$fragment;
    }

    private function absolute(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return $this->request->getSchemeAndHttpHost().'/'.ltrim($path, '/');
    }

    /** Markup and entities out; a crawler wants the words, not the HTML. */
    private function plain(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : $this->oneLine($text);
    }

    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
