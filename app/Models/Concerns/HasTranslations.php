<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The translation-table pattern (§7.1: "no opaque magic translation packages").
 *
 * A model using this trait declares a sibling `*_translations` model. Reading
 * a translated attribute goes through the row for the active locale.
 *
 * Deliberate behaviour, per §12: there is NO silent fallback to Arabic when an
 * English translation is missing. A missing translation returns null so the
 * caller can hide the page from the English site and sitemap rather than
 * quietly serving Arabic to an English-speaking visitor.
 *
 * @property-read Collection $translations
 */
trait HasTranslations
{
    /**
     * Translation model class. Convention: <Model>Translation in the same
     * namespace. Override translationModel() to deviate.
     */
    public static function translationModel(): string
    {
        return static::class.'Translation';
    }

    /** Foreign key on the translation table pointing back at this model. */
    public function translationForeignKey(): string
    {
        return $this->getForeignKey();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(static::translationModel(), $this->translationForeignKey());
    }

    /**
     * The translation row for one locale. Eager-load this rather than
     * `translations` when rendering a single-locale page — it keeps the
     * payload small and avoids N+1 (§7.4).
     */
    public function translation(?string $locale = null): HasOne
    {
        return $this->hasOne(static::translationModel(), $this->translationForeignKey())
            ->where('locale', $locale ?? app()->getLocale());
    }

    /**
     * Read one translated attribute for the active (or given) locale.
     * Returns null when the translation does not exist — never a fallback.
     */
    public function t(string $attribute, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        $row = $this->relationLoaded('translation') && $this->translation?->locale === $locale
            ? $this->translation
            : $this->translationFor($locale);

        return $row?->getAttribute($attribute);
    }

    public function translationFor(?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function hasTranslation(?string $locale = null): bool
    {
        return $this->translationFor($locale) !== null;
    }

    /**
     * Locales this record is actually publishable in — used by the sitemap
     * builder and the "translation missing" flag in the admin panel (§9.1).
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->pluck('locale')->unique()->values()->all();
    }

    /**
     * Scope: only records that have a translation in the given locale.
     * This is what keeps untranslated pages out of the English site.
     */
    public function scopeTranslatedIn($query, ?string $locale = null)
    {
        $locale ??= app()->getLocale();

        return $query->whereHas('translations', fn ($q) => $q->where('locale', $locale));
    }

    /** Eager-load only the active locale's translation. */
    public function scopeWithTranslation($query, ?string $locale = null)
    {
        return $query->with(['translation' => fn ($q) => $q->where(
            'locale',
            $locale ?? app()->getLocale()
        )]);
    }
}
