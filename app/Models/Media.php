<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Media Library's model, extended so alt text and captions are translatable
 * and — more importantly — eager-loadable.
 *
 * Without this relation every image on a page would cost one extra query to
 * fetch its alt text, which is exactly the N+1 §7.4 forbids.
 */
class Media extends BaseMedia
{
    public function translations(): HasMany
    {
        return $this->hasMany(MediaTranslation::class);
    }

    /**
     * Everywhere this image is referenced from — the count the delete warning
     * is built on.
     *
     * @return HasMany<MediaAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class);
    }

    /**
     * A thumbnail if one was generated, the original otherwise.
     *
     * The fallback is not defensive coding: images uploaded before the picker
     * existed, and images owned by records rather than the library, have no
     * conversions. A grid that showed them as broken squares would be telling
     * an editor their picture is gone when it is fine.
     */
    public function thumbUrl(): string
    {
        return $this->hasGeneratedConversion('thumb') ? $this->getUrl('thumb') : $this->getUrl();
    }

    public function translation(?string $locale = null): ?MediaTranslation
    {
        $locale ??= app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }
}
