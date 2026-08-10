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

    public function translation(?string $locale = null): ?MediaTranslation
    {
        $locale ??= app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }
}
