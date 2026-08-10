<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Throwable;

/**
 * Stores an image's pixel dimensions the moment it is uploaded.
 *
 * MediaResource hands `width` and `height` to every <img> so the browser can
 * reserve the box before the bytes arrive — that is most of the CLS budget in
 * §15.1. But nothing was ever writing those two custom properties, so every
 * image on the site rendered without them and pushed the content below it
 * down as it loaded. The resource was right; the data behind it was empty.
 *
 * It surfaced while importing 332 product photographs into one grid, where a
 * shift that is a nuisance on a hero becomes disqualifying. It was always
 * wrong — this fixes admin uploads and seeded media alike.
 *
 * Reads through the media library's own disk rather than a local path, so it
 * keeps working when uploads move to object storage (§16).
 */
class RecordMediaDimensions
{
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if (! $media instanceof Media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        // An SVG carries no raster size, and needs none: it scales to its
        // container, so no box has to be reserved for it.
        if ($media->mime_type === 'image/svg+xml') {
            return;
        }

        try {
            $contents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        } catch (Throwable) {
            return;
        }

        $size = is_string($contents) ? @getimagesizefromstring($contents) : false;

        if ($size === false) {
            return;
        }

        $media->setCustomProperty('width', $size[0]);
        $media->setCustomProperty('height', $size[1]);
        $media->save();
    }
}
