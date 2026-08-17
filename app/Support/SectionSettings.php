<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What each section type can be told, in its `settings` JSON.
 *
 * WHY THIS FILE EXISTS
 *
 * Thirteen keys already worked — `eyebrow`, `index_label`, `images`,
 * `secondaryLabel` and the rest — and not one of them was written down
 * anywhere the client could see. The panel offered a textarea labelled
 * "Section settings (JSON)" and nothing else, so the only way to learn that a
 * section could carry an eyebrow was to read the Vue component that renders
 * it. A capability nobody can discover is a capability nobody has.
 *
 * This is the same answer SettingsRegistry gave for the settings table, for
 * the same reason and in the same shape: structure lives here, the human
 * names live in resources/lang (§22.5), and a key absent from this list still
 * works — it is simply not advertised, so a component added tomorrow does not
 * break the panel by being ahead of this file.
 *
 * KEEPING IT HONEST
 *
 * Every key below is read by a component today. When one stops being read it
 * comes out of this list, because a panel that offers a setting the site
 * ignores is worse than one that offers nothing: the client fills it in and
 * waits for a change that never comes.
 */
class SectionSettings
{
    /**
     * Section type => the setting keys that type actually reads.
     *
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return [
            'hero' => ['eyebrow', 'image', 'ctaUrl', 'secondaryLabel', 'secondaryUrl', 'secondaryInterest'],
            'intro_statement' => ['index_label'],
            'solutions_grid' => ['eyebrow', 'index_label'],
            'gallery' => ['eyebrow', 'index_label', 'images'],
            'sector_spotlight' => ['eyebrow', 'index_label'],
            'story_carousel' => ['eyebrow', 'index_label'],
            'stats' => ['eyebrow'],
            'process_steps' => ['eyebrow', 'items'],
            'audience_split' => ['items'],
            'cards' => ['items'],
            'logos' => ['logos', 'group'],
            'cta_band' => ['eyebrow', 'firstFieldLabel', 'variants'],
        ];
    }

    /**
     * The keys one section type advertises.
     *
     * An unlisted type returns an empty list rather than throwing: the panel
     * then shows the JSON box with no hint, which is exactly what it did for
     * every type before this file existed.
     *
     * @return list<string>
     */
    public static function for(string $type): array
    {
        return self::map()[$type] ?? [];
    }
}
