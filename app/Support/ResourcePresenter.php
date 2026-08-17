<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * What one content entity looks like to the panel, derived from ContentRegistry.
 *
 * WHY THIS IS NOT IN ResourceController
 *
 * The controller answers HTTP: find the entity, check the permission, validate,
 * persist, redirect. Deciding that a record's human label is its first
 * translated field, or that images chosen from the library outrank the record's
 * own uploads, or which keys the bilingual editor needs in order to draw
 * itself — that is a second job, and it was the larger half of the file.
 *
 * Separating it is what lets the shape reaching the browser be asserted without
 * an HTTP round trip, and it keeps the two reasons this file changes apart: a
 * new route is not a new field, and a new field is not a new route.
 *
 * Nothing here changed in the move except its address. The reasoning written
 * against each decision travels with it, because that reasoning is the part
 * that was expensive to arrive at.
 */
class ResourcePresenter
{
    /**
     * Everything the editor needs to draw one record.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function payload(Model $record, array $config): array
    {
        $translations = [];
        $locales = array_keys(config('site.locales'));

        foreach ($locales as $locale) {
            $row = $record->translationFor($locale);

            foreach (array_keys($config['fields']) as $field) {
                $translations[$locale][$field] = $row?->getAttribute($field);
            }
        }

        $attributes = [];

        foreach (array_keys($config['attributes']) as $name) {
            $attributes[$name] = $record->getAttribute($name);
        }

        return [
            'id' => $record->getKey(),
            'active' => (bool) $record->{$config['flag']},
            'attributes' => $attributes,
            'translations' => $translations,
            'media' => self::media($record, $config, $locales),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $locales
     * @return array<string, mixed>
     */
    private static function media(Model $record, array $config, array $locales): array
    {
        $media = [];

        foreach ($config['media'] as $collection) {
            // Images chosen from the library first, the record's own uploads
            // only when there are none — the same order of authority the
            // public resources read through `mediaFor()`, so the panel and the
            // page cannot disagree about which picture is current.
            $attached = $record->attachedMedia($collection);

            $items = $attached->isNotEmpty() ? $attached : $record->getMedia($collection);

            $media[$collection] = $items
                ->map(function ($item) use ($locales): array {
                    $translations = [];

                    foreach ($locales as $locale) {
                        $row = $item->translation($locale);

                        $translations[$locale] = [
                            'alt_text' => $row?->alt_text,
                            'caption' => $row?->caption,
                        ];
                    }

                    return [
                        'id' => $item->id,
                        // getUrl(), not getFullUrl(): the latter prefixes APP_URL,
                        // so every thumbnail in the panel breaks the moment the
                        // site is opened on a host APP_URL does not name — the dev
                        // port, staging, or after the §16 domain move. Same reason
                        // MediaResource and SectionController use it. An <img>
                        // never needs the host.
                        'url' => $item->getUrl(),
                        'thumb' => $item->thumbUrl(),
                        'name' => $item->file_name,
                        'mime' => $item->mime_type,
                        'translations' => $translations,
                    ];
                })->values();
        }

        return $media;
    }

    /**
     * Options for every `relation:` attribute, so the form can render a
     * select rather than asking for a raw id.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, list<array{value: int, label: string}>>
     */
    public static function relationOptions(array $config): array
    {
        $options = [];

        foreach ($config['attributes'] as $name => $type) {
            if (! str_starts_with($type, 'relation:')) {
                continue;
            }

            $options[$name] = self::choicesFrom(
                ContentRegistry::get(substr($type, strlen('relation:'))),
            );
        }

        return $options;
    }

    /**
     * Choices for every many-to-many picker on this entity.
     *
     * Labelled from the target's own first translated field, exactly as the
     * single-relation selects are, so a segment renamed in its own editor
     * renames itself here too.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, list<array{value: int, label: string}>>
     */
    public static function taxonomyOptions(array $config): array
    {
        $options = [];

        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            $options[$name] = self::choicesFrom(ContentRegistry::get($taxonomy['entity']));
        }

        return $options;
    }

    /**
     * One list of value/label pairs, read from a target entity's own registry
     * entry. Shared by both pickers so a select and a multi-select can never
     * label the same record differently.
     *
     * @param  array<string, mixed>  $target
     * @return list<array{value: int, label: string}>
     */
    private static function choicesFrom(array $target): array
    {
        return $target['model']::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Model $r): array => [
                'value' => $r->getKey(),
                'label' => (string) self::titleOf($r, $target),
            ])
            ->all();
    }

    /**
     * The ids currently selected for each picker, in their pivot order.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, list<int>>
     */
    public static function taxonomyValues(?Model $record, array $config): array
    {
        $selected = [];

        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            // modelKeys() rather than pluck('<table>.id'): the relation knows
            // its own key, and naming the table here would silently return
            // nothing the day a second taxonomy is added.
            $selected[$name] = $record === null
                ? []
                : $record->{$taxonomy['relation']}()->get()->modelKeys();
        }

        return $selected;
    }

    /**
     * The first translated field is the record's human label.
     *
     * @param  array<string, mixed>  $config
     */
    public static function titleOf(Model $record, array $config): ?string
    {
        $first = array_key_first($config['fields']);

        return $record->t($first) ?? $record->slug ?? $record->name ?? "#{$record->getKey()}";
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function meta(array $config): array
    {
        return [
            'fields' => $config['fields'],
            // So the editor marks what it is going to refuse to save without,
            // rather than teaching it through an error after the fact.
            'required' => $config['required'] ?? [],
            'attributes' => $config['attributes'],
            'media' => $config['media'],
            'creatable' => $config['creatable'],
            'deletable' => $config['deletable'],
            'hasSections' => $config['hasSections'],
            // Label + entity per picker, so the form can title the field
            // without knowing what a taxonomy is.
            'taxonomies' => $config['taxonomies'] ?? [],
        ];
    }
}
