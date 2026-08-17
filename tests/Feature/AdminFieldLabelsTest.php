<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ContentRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every field in the bilingual editor is named in the editor's language (§22.5).
 *
 * All four bilingual screens rendered raw column names — `heading`, `cta url`,
 * `meta description` — to an Arabic editor, because `BilingualFields` fell back
 * to the field name whenever a screen forgot to pass `:labels`, and all four
 * forgot. The lookup now happens inside the component by the `admin.f.<field>`
 * convention, so the remaining way to reintroduce the bug is to add a field and
 * no key. That is what these tests watch.
 */
class AdminFieldLabelsTest extends TestCase
{
    /**
     * Every translatable field name the panel can render, gathered from the
     * places that actually define them rather than from a list kept by hand —
     * a hand-kept list would go stale exactly when a field is added, which is
     * the moment this test exists for.
     *
     * @return list<string>
     */
    private function fieldNames(): array
    {
        $names = [];

        // The content screens: field sets come from the registry.
        foreach (ContentRegistry::all() as $config) {
            $names = array_merge($names, array_keys($config['fields'] ?? []));
        }

        // The three screens that declare their own FIELDS in the component.
        $screens = [
            'resources/js/Pages/Admin/Pages/Edit.vue',
            'resources/js/Pages/Admin/Campaigns/Edit.vue',
            'resources/js/Pages/Admin/Sections/Builder.vue',
        ];

        foreach ($screens as $screen) {
            $source = (string) file_get_contents(base_path($screen));

            if (preg_match('/const FIELDS = \{(.*?)\};/s', $source, $block) !== 1) {
                $this->fail("Could not find a FIELDS block in {$screen}. If the shape changed, this test must follow it.");
            }

            preg_match_all('/(\w+)\s*:/', $block[1], $found);
            $names = array_merge($names, $found[1]);
        }

        return array_values(array_unique($names));
    }

    #[Test]
    public function every_editable_field_has_an_arabic_name(): void
    {
        $labels = (array) __('admin.f', locale: 'ar');

        $missing = array_values(array_diff($this->fieldNames(), array_keys($labels)));

        $this->assertSame([], $missing,
            'These fields would render their raw column name to an Arabic editor. '
            .'Add them under `f` in resources/lang/ar/admin.php.');
    }

    #[Test]
    public function every_editable_field_has_a_hint_telling_the_editor_what_to_type(): void
    {
        // The client's point: a data-entry screen that names a box but does not
        // say what belongs in it has only moved the confusion.
        $hints = (array) __('admin.ph', locale: 'ar');

        $missing = array_values(array_diff($this->fieldNames(), array_keys($hints)));

        $this->assertSame([], $missing,
            'These fields offer the editor no hint about what to type. '
            .'Add them under `ph` in resources/lang/ar/admin.php.');
    }

    #[Test]
    public function both_languages_name_the_same_fields(): void
    {
        foreach (['f', 'ph'] as $group) {
            $this->assertSame(
                array_keys((array) __("admin.{$group}", locale: 'ar')),
                array_keys((array) __("admin.{$group}", locale: 'en')),
                "admin.{$group} does not carry the same fields in both languages."
            );
        }
    }

    #[Test]
    public function the_component_resolves_names_itself_rather_than_trusting_each_screen(): void
    {
        /*
         * The regression that matters is not a missing string — it is going
         * back to a prop four screens have to remember. Three of the four
         * never passed `:labels`, and nothing failed; the editor simply read
         * English.
         */
        $source = (string) file_get_contents(base_path('resources/js/Components/admin/BilingualFields.vue'));

        $this->assertStringContainsString('admin.f.', $source,
            'BilingualFields must resolve field names by convention, not rely on a :labels prop.');
        $this->assertStringContainsString('admin.ph.', $source,
            'BilingualFields must resolve field hints by convention.');
    }

    #[Test]
    public function the_textarea_carries_its_hint_like_every_other_control(): void
    {
        // `input` and `select` bound `placeholder`; the textarea did not — so
        // body, excerpt and meta description, the fields with the most room to
        // explain themselves, were the ones that explained nothing.
        $source = (string) file_get_contents(base_path('resources/js/Components/admin/Field.vue'));

        $textarea = (string) preg_replace('/.*<textarea(.*?)\/>.*/s', '$1', $source);

        $this->assertStringContainsString('placeholder', $textarea,
            'The textarea must bind :placeholder, or half the panel loses its hints.');
    }
}
