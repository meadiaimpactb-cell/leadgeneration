<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ResourceRules;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The rules behind every content screen (§9.1, §12).
 *
 * These were private methods on ResourceController, reachable only through a
 * full HTTP round trip with an authenticated administrator and a seeded
 * record. The behaviour they encode is subtle enough to deserve being asked
 * directly — particularly the bilingual rule, which is the one that decides
 * whether an English column left blank means "not translated yet" or "you have
 * forgotten something".
 *
 * No database and no application: this is a pure function of the registry's
 * config, and it should stay one.
 */
class ResourceRulesTest extends TestCase
{
    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'attributes' => ['slug' => 'slug', 'sort_order' => 'number', 'link' => 'url'],
            'fields' => ['name' => 'text', 'body' => 'html', 'outcome' => 'text'],
            'required' => ['outcome'],
            'flag' => 'is_active',
        ];
    }

    #[Test]
    public function a_field_an_entity_cannot_be_described_without_is_required_only_where_writing_has_started(): void
    {
        $rules = ResourceRules::presenceFor($this->config(), 'en', 'outcome');

        // Required *with its siblings in the same column* — so an English side
        // left entirely blank still means "not translated" and deletes its row,
        // while a half-written one is held to the whole.
        $this->assertSame(['required_with:translations.en.name,translations.en.body'], $rules);
    }

    #[Test]
    public function an_ordinary_field_is_never_made_compulsory(): void
    {
        $this->assertSame([], ResourceRules::presenceFor($this->config(), 'ar', 'name'));
    }

    /**
     * The rule that keeps a record honestly untranslated. Without `nullable`
     * every blank box would fail `string`, because an empty input arrives as
     * null — and §12 would lose the state it depends on.
     */
    #[Test]
    public function every_translated_field_stays_nullable(): void
    {
        $rules = ResourceRules::for($this->config(), ['ar', 'en']);

        foreach (['ar', 'en'] as $locale) {
            foreach (['name', 'body', 'outcome'] as $field) {
                $this->assertContains('nullable', $rules["translations.{$locale}.{$field}"],
                    "translations.{$locale}.{$field} must stay nullable, or a blank box becomes an error.");
            }
        }
    }

    #[Test]
    public function a_short_field_and_a_long_one_are_given_different_ceilings(): void
    {
        $rules = ResourceRules::for($this->config(), ['ar']);

        $this->assertContains('max:255', $rules['translations.ar.name']);
        $this->assertContains('max:20000', $rules['translations.ar.body']);
    }

    #[Test]
    public function each_attribute_type_carries_its_own_rules(): void
    {
        $this->assertSame(['required', 'string', 'max:191'], ResourceRules::forAttribute('slug'));
        $this->assertSame(['nullable', 'numeric'], ResourceRules::forAttribute('number'));
        $this->assertSame(['nullable', 'url', 'max:512'], ResourceRules::forAttribute('url'));
        $this->assertSame(['nullable', 'integer'], ResourceRules::forAttribute('relation:sectors'));
        $this->assertSame(['nullable', 'string', 'max:255'], ResourceRules::forAttribute('anything-else'));
    }

    /**
     * ContentRegistry writes `enum:a,b,c` and this turns it into the `in` rule.
     * Worth pinning: `enum` is not Laravel's Enum rule here, and reading it as
     * one would send an untranslated «validation.enum» to an Arabic editor.
     */
    #[Test]
    public function an_enum_field_becomes_the_in_rule_over_its_own_options(): void
    {
        $this->assertSame(
            ['required', 'string', 'in:partner,accreditation,client'],
            ResourceRules::forAttribute('enum:partner,accreditation,client'),
        );
    }

    #[Test]
    public function a_taxonomy_picker_only_accepts_ids_that_exist(): void
    {
        $config = $this->config() + ['taxonomies' => [
            'sectors' => ['table' => 'sectors', 'relation' => 'sectors'],
        ]];

        $rules = ResourceRules::for($config, ['ar']);

        $this->assertSame(['array'], $rules['taxonomies.sectors']);
        $this->assertSame(['integer', 'exists:sectors,id'], $rules['taxonomies.sectors.*']);
    }
}
