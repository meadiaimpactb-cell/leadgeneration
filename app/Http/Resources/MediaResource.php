<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An image as the frontend needs it (§13, §10.8).
 *
 * Always carries explicit width/height so the browser reserves the box and
 * CLS stays inside budget, plus the alt text the client entered for this
 * locale. A decorative image stores an empty alt on purpose; null means the
 * alt was never filled in, which the admin panel flags.
 *
 * Callers should eager-load `media.translations` — otherwise reading the alt
 * text costs one query per image.
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /** @return array<string, mixed>|null */
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        $translation = $this->translation();

        return [
            'id' => $this->id,
            // Root-relative, not absolute. getFullUrl() prefixes APP_URL, which
            // silently breaks every image the moment the site is reached on a
            // different host — a dev port, staging, or the §16 domain move. An
            // <img> never needs the host anyway.
            'url' => $this->getUrl(),
            'webp' => $this->hasGeneratedConversion('webp') ? $this->getUrl('webp') : null,
            'thumb' => $this->hasGeneratedConversion('thumb') ? $this->getUrl('thumb') : null,
            'width' => $this->getCustomProperty('width'),
            'height' => $this->getCustomProperty('height'),
            'alt' => $translation?->alt_text,
            'caption' => $translation?->caption,
        ];
    }
}
