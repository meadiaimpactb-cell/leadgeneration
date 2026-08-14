<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Section;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Solution;
use App\Services\Seo\SchemaBuilder;
use App\Support\Settings;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DemoExtrasSeeder;
use Database\Seeders\LeadFieldsSeeder;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\StructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schema.org structured data (§13).
 *
 * Structured data is a set of claims made to Google in the client's name, and
 * Google checks them against the visible page. So the tests that matter are
 * less about "is the tag there" and more about "does it ever say something
 * nobody typed" — a fabricated address, an FAQ from the other language, a
 * service page for an audience segment.
 */
class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            StructureSeeder::class,
            NavigationSeeder::class,
            LeadFieldsSeeder::class,
            DemoContentSeeder::class,
            DemoExtrasSeeder::class,
        ]);
    }

    /** The JSON-LD block on a rendered page, decoded. */
    private function graphFor(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<script type="application\/ld\+json">/',
            $html,
            'The page carries no structured data at all.',
        );

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        $decoded = json_decode($m[1], true);

        $this->assertIsArray($decoded, 'The structured data is not valid JSON.');

        return $decoded['@graph'];
    }

    /** @return array<string, mixed>|null */
    private function node(array $graph, string $type): ?array
    {
        foreach ($graph as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ //
    // The company
    // ------------------------------------------------------------------ //

    #[Test]
    public function every_page_states_who_the_company_is(): void
    {
        $graph = $this->graphFor('/ar');

        $org = $this->node($graph, 'LocalBusiness');

        $this->assertNotNull($org, 'No organisation node — Google has nothing to attach the brand to.');
        $this->assertNotEmpty($org['name']);
        $this->assertSame(url('/'), $org['url']);
    }

    #[Test]
    public function the_company_is_only_a_local_business_when_it_has_an_address(): void
    {
        // `contact.address.ar` is group `contact`, key `address.ar` — the
        // lookup name is the two columns joined, not one column.
        Setting::query()->where('group', 'contact')->where('key', 'like', 'address%')->delete();

        // A query-builder delete fires no model events, so the settings cache
        // still holds the address it was asked to forget.
        app(Settings::class)->forget();

        $graph = $this->graphFor('/ar');

        $this->assertNull(
            $this->node($graph, 'LocalBusiness'),
            'A shopfront was claimed for a company with no address on file.',
        );
        $this->assertNotNull($this->node($graph, 'Organization'));
    }

    /** The thing that must never happen: a value nobody entered. */
    #[Test]
    public function a_field_with_no_value_is_left_out_rather_than_invented(): void
    {
        $org = $this->node($this->graphFor('/ar'), 'LocalBusiness');

        // No logo has been uploaded in this fixture, so the key must be absent
        // entirely — not an empty string, not a placeholder path.
        $this->assertArrayNotHasKey('logo', $org);

        // Opening hours are stored as a sentence and are deliberately never
        // guessed into schema.org's format.
        $this->assertArrayNotHasKey('openingHours', $org);
        $this->assertArrayNotHasKey('openingHoursSpecification', $org);
    }

    #[Test]
    public function the_social_accounts_are_tied_to_the_company(): void
    {
        $org = $this->node($this->graphFor('/ar'), 'LocalBusiness');

        $this->assertNotEmpty($org['sameAs']);

        foreach ($org['sameAs'] as $url) {
            $this->assertStringStartsWith('http', $url);
        }
    }

    // ------------------------------------------------------------------ //
    // The trail
    // ------------------------------------------------------------------ //

    #[Test]
    public function a_page_one_level_deep_has_no_breadcrumb_list(): void
    {
        // The home page is the trail's own root; a one-item list says nothing.
        $this->assertNull($this->node($this->graphFor('/ar'), 'BreadcrumbList'));
    }

    #[Test]
    public function a_deeper_page_publishes_its_trail_in_order(): void
    {
        $solution = Solution::query()->visible()->firstOrFail();

        $crumbs = $this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'BreadcrumbList');

        $this->assertNotNull($crumbs);

        $positions = array_column($crumbs['itemListElement'], 'position');

        $this->assertSame(range(1, count($positions)), $positions,
            'The breadcrumb positions are not a 1..n sequence, which Google rejects.');

        // The last crumb is the page you are on, so it carries no link.
        $this->assertArrayNotHasKey('item', end($crumbs['itemListElement']));
    }

    // ------------------------------------------------------------------ //
    // The questions
    // ------------------------------------------------------------------ //

    #[Test]
    public function an_accordion_becomes_an_faq_a_search_engine_can_read(): void
    {
        $solution = $this->solutionWithAnAccordion();

        $faq = $this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'FAQPage');

        $this->assertNotNull($faq, 'A page with an FAQ block published no FAQ markup.');
        $this->assertNotEmpty($faq['mainEntity']);

        foreach ($faq['mainEntity'] as $question) {
            $this->assertNotEmpty($question['name']);
            $this->assertNotEmpty($question['acceptedAnswer']['text']);
        }
    }

    /** §12: the English page must never carry the Arabic answers. */
    #[Test]
    public function the_faq_answers_are_in_the_language_being_served(): void
    {
        $solution = $this->solutionWithAnAccordion();

        $arabic = $this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'FAQPage');
        $english = $this->node($this->graphFor("/en/solutions/{$solution->slug}"), 'FAQPage');

        $this->assertNotNull($english);
        $this->assertNotSame(
            $arabic['mainEntity'][0]['name'],
            $english['mainEntity'][0]['name'],
            'The English page is serving the Arabic questions.',
        );
    }

    #[Test]
    public function a_question_with_no_answer_is_not_published(): void
    {
        $solution = $this->solutionWithAnAccordion();

        $section = Section::query()
            ->where('sectionable_type', Solution::class)
            ->where('sectionable_id', $solution->id)
            ->where('type', 'accordion')
            ->firstOrFail();

        $items = $section->settings['items'];
        $items[] = ['question' => 'سؤال بلا جواب', 'answer' => ''];

        $section->forceFill(['settings' => ['items' => $items]])->save();

        $faq = $this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'FAQPage');

        foreach ($faq['mainEntity'] as $question) {
            $this->assertNotSame('سؤال بلا جواب', $question['name'],
                'An unanswered question was published as an answered one.');
        }
    }

    // ------------------------------------------------------------------ //
    // What is offered, and by whom
    // ------------------------------------------------------------------ //

    #[Test]
    public function a_solution_is_marked_up_as_a_service_the_company_provides(): void
    {
        $solution = Solution::query()->visible()->firstOrFail();

        $service = $this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'Service');

        $this->assertNotNull($service);
        $this->assertSame($solution->t('name'), $service['name']);
        $this->assertSame(url('/').'#organization', $service['provider']['@id'],
            'The service is not tied back to the company that provides it.');
    }

    /** An audience is not a service — typing it as one misstates the business. */
    #[Test]
    public function an_audience_segment_is_not_marked_up_as_a_service(): void
    {
        $sector = Sector::query()->visible()->firstOrFail();

        $this->assertNull(
            $this->node($this->graphFor("/ar/solutions/{$sector->slug}"), 'Service'),
            'A segment page claims the company sells an audience.',
        );
    }

    // ------------------------------------------------------------------ //
    // Safety of the emitted document
    // ------------------------------------------------------------------ //

    #[Test]
    public function a_value_containing_markup_cannot_close_the_script_tag(): void
    {
        $solution = $this->solutionWithAnAccordion();

        $section = Section::query()
            ->where('sectionable_type', Solution::class)
            ->where('sectionable_id', $solution->id)
            ->where('type', 'accordion')
            ->firstOrFail();

        $section->forceFill(['settings' => ['items' => [[
            'question' => 'سؤال </script><script>alert(1)</script>',
            'answer' => 'جواب عادي',
        ]]]])->save();

        $html = $this->get("/ar/solutions/{$solution->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html,
            'A database value broke out of the JSON-LD tag.');

        // And the block is still valid JSON after the escaping.
        $this->assertNotNull($this->node($this->graphFor("/ar/solutions/{$solution->slug}"), 'FAQPage'));
    }

    #[Test]
    public function the_builder_returns_nothing_when_there_is_no_company_to_describe(): void
    {
        Setting::query()->where('group', 'site')->where('key', 'like', 'name%')->delete();
        Setting::query()->where('group', 'contact')->delete();
        app(Settings::class)->forget();

        $json = app(SchemaBuilder::class)->build();

        // WebSite still stands on config('app.name'), but nothing may be
        // asserted about a company whose name nobody has entered.
        $this->assertStringNotContainsString('"LocalBusiness"', (string) $json);
    }

    private function solutionWithAnAccordion(): Solution
    {
        $section = Section::query()
            ->where('sectionable_type', Solution::class)
            ->where('type', 'accordion')
            ->where('is_active', true)
            ->firstOrFail();

        return Solution::query()->findOrFail($section->sectionable_id);
    }
}
