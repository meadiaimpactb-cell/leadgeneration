<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The four sizes every image on this site is served in (§13, §14).
 *
 * WHY THIS IS A TRAIT AND NOT SIX COPIES
 *
 * It was one copy, on Setting, and the library images it governs came out
 * correct. Every other model that owns images — a partner's logo, an artisan's
 * portrait, a solution's hero — registered no conversions at all, so those
 * images were served at whatever size they were uploaded: twenty-four of the
 * thirty-one files on the site, at full original weight, on the pages a buyer
 * lands on first. Nothing looked wrong, because a 2MB JPEG renders exactly
 * like a 60KB one.
 *
 * §13 asks for WebP and §14 for page speed; both were true only for the
 * seventh of the images that happened to be uploaded through the library.
 *
 * THE SIZES
 *
 * `thumb` is a square crop for panel tiles. `medium` and `large` are the two
 * widths `srcset` offers a browser, so a phone stops downloading a 1600px file
 * to paint it 400px wide. `webp` is the format fallback for browsers that
 * cannot take AVIF.
 */
trait HasImageConversions
{
    public function registerMediaConversions(?Media $media = null): void
    {
        /*
         * Nothing to resize on a video. Spatie's video generator needs ffmpeg,
         * which is not installed here, so without this guard every hero clip
         * would queue four conversions that can only fail.
         */
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 400)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->fit(Fit::Max, 800, 800);

        $this->addMediaConversion('large')
            ->fit(Fit::Max, 1600, 1600);

        $this->addMediaConversion('webp')
            ->fit(Fit::Max, 1600, 1600)
            ->format('webp');

        /*
         * The small end of the `srcset` pair, in WebP so both entries are the
         * same format.
         *
         * A srcset that mixed a JPEG at 800w with a WebP at 1600w is legal and
         * wrong-headed: the browser chooses on width alone, so a phone would
         * take the heavier format of the two. Offering one format at two widths
         * lets it choose the only thing it should be choosing — how many pixels
         * it actually needs.
         */
        $this->addMediaConversion('webp_small')
            ->fit(Fit::Max, 800, 800)
            ->format('webp');
    }
}
