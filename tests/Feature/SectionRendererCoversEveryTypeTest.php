<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Section;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A section type the panel offers must be one the site can draw, and a type
 * the site draws must be one the panel offers (§0.3, §9.1).
 *
 * Two lists have to agree and live in different languages:
 *
 *   · `Section::TYPES` — what the panel validates against and builds its
 *     "add section" picker from;
 *   · `COMPONENTS` in SectionRenderer.vue — what the browser knows how to
 *     render.
 *
 * They had already drifted. `bridge_model` and `team` were rendering on
 * /about, with rows in the database and components in the renderer, and were
 * absent from `Section::TYPES` — so the client could see both blocks on their
 * own site and had no way to add another, or to get one back after deleting
 * it. Nothing failed loudly; the picker simply never listed them.
 *
 * The reverse gap is just as bad and harder to notice: a type in the picker
 * with no component renders nothing at all, so an editor adds a block, saves,
 * and finds the page unchanged with no error to explain it.
 */
class SectionRendererCoversEveryTypeTest extends TestCase
{
    /**
     * The keys of `COMPONENTS` in SectionRenderer.vue.
     *
     * Read out of the file rather than duplicated here: a copy in this test
     * would be a third list to keep in step, which is the problem, not the fix.
     *
     * @return list<string>
     */
    private function renderableTypes(): array
    {
        $source = file_get_contents(resource_path('js/Components/sections/SectionRenderer.vue')) ?: '';

        $this->assertMatchesRegularExpression(
            '/const COMPONENTS = \{/',
            $source,
            'SectionRenderer no longer declares COMPONENTS; this test needs updating with it.',
        );

        $map = substr($source, (int) strpos($source, 'const COMPONENTS = {'));
        $map = substr($map, 0, (int) strpos($map, '};'));

        preg_match_all('/^\s*([a-z_]+):\s*\w+,/m', $map, $matches);

        return $matches[1];
    }

    /**
     * Types rendered by a page component directly rather than through
     * SectionRenderer — Home, Impact, Training and the sector pages each read
     * their own blocks by name. They are legitimately absent from COMPONENTS.
     *
     * @return list<string>
     */
    private function renderedByPagesDirectly(): array
    {
        $types = [];

        foreach (glob(resource_path('js/Pages/Public/*.vue')) as $file) {
            preg_match_all("/section\('([a-z_]+)'\)/", file_get_contents($file) ?: '', $m);
            $types = array_merge($types, $m[1]);
        }

        return array_values(array_unique($types));
    }

    #[Test]
    public function every_renderable_type_is_one_the_panel_can_create(): void
    {
        foreach ($this->renderableTypes() as $type) {
            $this->assertContains(
                $type,
                Section::TYPES,
                "SectionRenderer draws `{$type}`, but the panel's picker never offers it.",
            );
        }
    }

    /**
     * `map` is offered by the panel and drawn by nothing, and that is a live
     * decision rather than an oversight.
     *
     * `MapBlock.vue` exists and is imported nowhere; the footer draws the
     * showroom map from `settings` itself, and /contact explicitly skips the
     * `map` section for that reason. A `map` row already exists on the contact
     * page. Registering the component would put a second map on a page that
     * already has one; dropping the type would remove the only route back if
     * the client ever wants a map somewhere else.
     *
     * Both are the client's call, so this records the state instead of
     * quietly picking one. When they decide, this entry goes and the
     * assertion below covers `map` like everything else.
     *
     * @return list<string>
     */
    private function knownUndrawn(): array
    {
        return ['map'];
    }

    #[Test]
    public function every_type_the_panel_offers_can_actually_be_drawn(): void
    {
        $drawable = array_merge($this->renderableTypes(), $this->renderedByPagesDirectly());

        foreach (Section::TYPES as $type) {
            if (in_array($type, $this->knownUndrawn(), true)) {
                continue;
            }

            $this->assertContains(
                $type,
                $drawable,
                "The panel offers `{$type}`, but nothing renders it — an editor would add it and see no change.",
            );
        }
    }

    /**
     * And the exception list must not become a dumping ground: an entry that
     * has quietly been fixed should be removed, not left describing a problem
     * that no longer exists.
     */
    #[Test]
    public function the_undrawn_list_still_describes_reality(): void
    {
        $drawable = array_merge($this->renderableTypes(), $this->renderedByPagesDirectly());

        foreach ($this->knownUndrawn() as $type) {
            $this->assertNotContains(
                $type,
                $drawable,
                "`{$type}` renders now — take it out of knownUndrawn().",
            );
        }
    }

    /**
     * The two that started this. Named explicitly so the regression reads as
     * itself in the output rather than as a count that happens to be wrong.
     */
    #[Test]
    public function the_about_page_blocks_are_creatable_from_the_panel(): void
    {
        $this->assertContains('bridge_model', Section::TYPES);
        $this->assertContains('team', Section::TYPES);
    }

    /**
     * A third way the same list can drift: the type exists, it renders, and
     * the panel shows it under its programmatic key.
     *
     * The picker used to print `ty` verbatim, so an editor chose between
     * `cta_band` and `sector_spotlight` — Latin keys, in a Latin face, inside
     * an Arabic right-to-left screen. §9.1 requires the panel to be usable
     * with no technical help, and a key is technical help by another name.
     *
     * Asserting both locales because §12 forbids falling back between them:
     * an English-speaking editor given an Arabic label is the same failure in
     * the other direction.
     */
    #[Test]
    public function every_section_type_has_a_name_an_editor_can_read(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $strings = require lang_path($locale.'/admin.php');

            foreach (Section::TYPES as $type) {
                $this->assertArrayHasKey(
                    "section_type_{$type}",
                    $strings,
                    "`{$type}` has no name in {$locale}; the panel would fall back to the raw key.",
                );

                $this->assertNotSame(
                    '',
                    trim((string) $strings["section_type_{$type}"]),
                    "`{$type}`'s name in {$locale} is empty.",
                );
            }
        }
    }

    /**
     * And a name is not enough on its own.
     *
     * «بطاقات» and «شبكة الحلول» are both a row of boxes to anyone who has not
     * seen them rendered. The one-line description under the picker is what
     * makes the choice a decision rather than a guess, so it is held to the
     * same standard as the name.
     */
    #[Test]
    public function every_section_type_says_what_it_puts_on_the_page(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $strings = require lang_path($locale.'/admin.php');

            foreach (Section::TYPES as $type) {
                $this->assertArrayHasKey(
                    "section_type_hint_{$type}",
                    $strings,
                    "`{$type}` has no description in {$locale}; the picker would offer a name with nothing behind it.",
                );
            }
        }
    }
}
