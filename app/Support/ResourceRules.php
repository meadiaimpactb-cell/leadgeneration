<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The validation rules for one content entity, derived from ContentRegistry.
 *
 * WHY THIS IS NOT IN ResourceController
 *
 * The controller's job is the HTTP one: find the entity, check the permission,
 * hand the request somewhere, redirect. Working out that a `url` field takes
 * `nullable|url|max:512`, or that the outcome line on a training track becomes
 * compulsory only for a locale somebody has actually started writing, is a
 * separate decision — and it is the one worth testing directly. As a private
 * method it could only be reached through a full HTTP round trip with an
 * authenticated administrator and a seeded record; here it is a pure function
 * of the registry's config, and a test can ask it a question and read the
 * answer.
 *
 * Nothing about the rules themselves changed in the move. The reasoning that
 * was written against each of them travels with them, because that reasoning
 * is the expensive part.
 */
class ResourceRules
{
    /**
     * @param  array<string, mixed>  $config  one entry from ContentRegistry
     * @param  list<string>  $locales
     * @return array<string, list<string>>
     */
    public static function for(array $config, array $locales): array
    {
        $rules = ['active' => ['boolean']];

        foreach ($config['attributes'] as $name => $type) {
            $rules["attributes.{$name}"] = self::forAttribute($type);
        }

        // Many-to-many pickers, e.g. the audience segments a solution serves.
        foreach ($config['taxonomies'] ?? [] as $name => $taxonomy) {
            $rules["taxonomies.{$name}"] = ['array'];
            $rules["taxonomies.{$name}.*"] = ['integer', 'exists:'.$taxonomy['table'].',id'];
        }

        foreach ($locales as $locale) {
            foreach ($config['fields'] as $field => $type) {
                $rules["translations.{$locale}.{$field}"] = array_merge(
                    ['nullable'],
                    self::presenceFor($config, $locale, $field),
                    ['string', $type === 'text' ? 'max:255' : 'max:20000'],
                );
            }
        }

        return $rules;
    }

    /**
     * What makes one translated field compulsory, if anything does.
     *
     * Every field stays `nullable`, because a record that exists in one
     * language and not the other is normal and §12 wants it stored that way —
     * and because empty strings arrive here as null, so dropping `nullable`
     * makes `string` fail on every blank box.
     *
     * An entity may name fields it cannot be described without — the outcome
     * line on a training track — and those become required *for a locale being
     * written*, never for a locale left alone. `required_with` is an implicit
     * rule, so it still fires alongside `nullable`, and it fires only once
     * something else in that same column has been typed: a blank English side
     * still means "not translated" and still deletes its row.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function presenceFor(array $config, string $locale, string $field): array
    {
        if (! in_array($field, $config['required'] ?? [], true)) {
            return [];
        }

        $siblings = array_map(
            fn (string $other): string => "translations.{$locale}.{$other}",
            array_values(array_diff(array_keys($config['fields']), [$field])),
        );

        return ['required_with:'.implode(',', $siblings)];
    }

    /** @return list<string> */
    public static function forAttribute(string $type): array
    {
        return match (true) {
            $type === 'number' => ['nullable', 'numeric'],
            $type === 'url' => ['nullable', 'url', 'max:512'],
            $type === 'slug' => ['required', 'string', 'max:191'],
            str_starts_with($type, 'relation:') => ['nullable', 'integer'],
            str_starts_with($type, 'enum:') => ['required', 'string',
                'in:'.substr($type, strlen('enum:'))],
            default => ['nullable', 'string', 'max:255'],
        };
    }
}
