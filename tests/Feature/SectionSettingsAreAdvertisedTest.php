<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Section;
use App\Support\SectionSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The section-settings reference must describe the site, not a memory of it.
 *
 * `SectionSettings` tells the panel which keys a section type understands, so
 * an editor can use `eyebrow` or `index_label` without reading a Vue file.
 * That is only worth having while it is TRUE: a panel advertising a setting
 * the site ignores is worse than one advertising nothing, because the client
 * fills it in and waits for a change that never comes.
 *
 * The first version of the map was written from memory and got four keys
 * wrong — `group` was attributed to `contact_block` when only `logos` reads
 * it, `variants` and `secondaryInterest` were on the wrong types, and three
 * types were missing. These tests exist so the next edit cannot repeat that
 * silently.
 */
class SectionSettingsAreAdvertisedTest extends TestCase
{
    /** Every `x.settings?.key` in the public pages, resolved to its type. */
    private function keysReadByTheSite(): array
    {
        $owners = [];

        foreach (glob(resource_path('js/Pages/Public/*.vue')) as $file) {
            $source = file_get_contents($file) ?: '';

            // `const heroCopy = computed(() => section('hero'))`
            preg_match_all(
                "/const\s+(\w+)\s*=\s*computed\(\(\)\s*=>\s*section\('([a-z_]+)'\)/",
                $source,
                $vars,
                PREG_SET_ORDER,
            );

            $varToType = [];
            foreach ($vars as $v) {
                $varToType[$v[1]] = $v[2];
            }

            // `heroCopy.settings?.eyebrow` and `heroCopy.value.settings?.eyebrow`
            preg_match_all('/\b(\w+)(?:\.value)?\.settings\?\.(\w+)/', $source, $uses, PREG_SET_ORDER);

            foreach ($uses as $u) {
                if (isset($varToType[$u[1]])) {
                    $owners[$varToType[$u[1]]][] = $u[2];
                }
            }

            // `s.type === 'logos' && s.settings?.group`
            preg_match_all("/s\.type === '([a-z_]+)' && s\.settings\?\.(\w+)/", $source, $direct, PREG_SET_ORDER);

            foreach ($direct as $d) {
                $owners[$d[1]][] = $d[2];
            }
        }

        return array_map(fn (array $keys): array => array_values(array_unique($keys)), $owners);
    }

    /**
     * Nothing is advertised that the site does not read.
     *
     * `index_label` is the one exception and it is deliberate: it is resolved
     * in Home.vue's `caption()` helper by section TYPE rather than through a
     * `x.settings?.` expression, so the scan above cannot see it.
     */
    #[Test]
    public function every_advertised_key_is_one_the_site_actually_reads(): void
    {
        $actual = $this->keysReadByTheSite();

        foreach (SectionSettings::map() as $type => $advertised) {
            foreach ($advertised as $key) {
                if ($key === 'index_label') {
                    continue;
                }

                $this->assertContains(
                    $key,
                    $actual[$type] ?? [],
                    "The panel offers `{$key}` on a {$type} section, but nothing reads it.",
                );
            }
        }
    }

    /** And nothing the site reads is left unadvertised. */
    #[Test]
    public function every_key_the_site_reads_is_advertised(): void
    {
        foreach ($this->keysReadByTheSite() as $type => $keys) {
            foreach ($keys as $key) {
                $this->assertContains(
                    $key,
                    SectionSettings::for($type),
                    "A {$type} section reads `{$key}`, but the panel never mentions it.",
                );
            }
        }
    }

    /** A type that does not exist cannot be described. */
    #[Test]
    public function the_map_only_names_real_section_types(): void
    {
        foreach (array_keys(SectionSettings::map()) as $type) {
            $this->assertContains($type, Section::TYPES, "`{$type}` is not a section type.");
        }
    }

    /** Every advertised key has a sentence explaining it, in both languages. */
    #[Test]
    public function every_advertised_key_is_explained_in_both_languages(): void
    {
        $keys = array_unique(array_merge(...array_values(SectionSettings::map())));

        foreach ($keys as $key) {
            foreach (['ar', 'en'] as $locale) {
                $line = __("admin.section_setting_{$key}", [], $locale);

                $this->assertNotSame(
                    "admin.section_setting_{$key}",
                    $line,
                    "`{$key}` is offered in the panel with no {$locale} explanation.",
                );
            }
        }
    }

    /** An unknown type is not an error — it simply advertises nothing. */
    #[Test]
    public function an_unlisted_type_advertises_nothing(): void
    {
        $this->assertSame([], SectionSettings::for('video'));
        $this->assertSame([], SectionSettings::for('not_a_real_type'));
    }
}
