<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Media;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Images referenced from the media library, in a chosen order.
 *
 * The counterpart to Media Library's own `media` relation: that one is for
 * images this record OWNS, this one is for images it USES. A record can have
 * both, and the payload methods prefer the referenced ones — an editor who
 * picks an image from the library means it to replace whatever was there.
 *
 * @see MediaAttachment
 */
trait HasAttachedMedia
{
    /**
     * Referenced images are always loaded with the record.
     *
     * Eloquent calls this for every model using the trait, which is the only
     * arrangement that holds: `Model::preventLazyLoading()` is on in local
     * (§7.4), so a controller that forgot to eager-load would throw in
     * development and silently issue a query per row in production. Eleven
     * controllers render sections and nine screens render content records —
     * this is one place instead of twenty chances to miss one.
     */
    public function initializeHasAttachedMedia(): void
    {
        $this->with = array_values(array_unique(
            array_merge($this->with, ['mediaAttachments.media.translations'])
        ));
    }

    /**
     * The image to use for a slot: the one chosen from the library, or the one
     * uploaded onto this record before the library existed.
     *
     * The single accessor every resource goes through, so "which image wins"
     * is answered once rather than in each of the nine screens that ask.
     */
    public function mediaFor(string $collection): ?Media
    {
        return $this->attachedMedia($collection)->first() ?? $this->getFirstMedia($collection);
    }

    /** @return MorphMany<MediaAttachment, $this> */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The referenced images in one slot, in their configured order.
     *
     * @return Collection<int, Media>
     */
    public function attachedMedia(string $collection): Collection
    {
        return $this->mediaAttachments
            ->where('collection', $collection)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (MediaAttachment $attachment): ?Media => $attachment->media)
            ->filter()
            ->values();
    }

    /**
     * Replace everything in a slot with this list, in this order.
     *
     * Written as delete-then-insert inside a transaction rather than a diff:
     * the list the panel sends IS the desired state, and reconciling it row by
     * row is more code and more ways to end up half-applied.
     *
     * @param  list<int>  $mediaIds
     */
    public function syncAttachedMedia(string $collection, array $mediaIds): void
    {
        DB::transaction(function () use ($collection, $mediaIds): void {
            $this->mediaAttachments()->where('collection', $collection)->delete();

            // array_unique before insert: the unique index would reject a
            // repeat anyway, and failing the whole save because someone
            // double-clicked "insert" is not an error worth showing.
            foreach (array_values(array_unique($mediaIds)) as $order => $mediaId) {
                $this->mediaAttachments()->create([
                    'media_id' => $mediaId,
                    'collection' => $collection,
                    'sort_order' => $order,
                ]);
            }
        });

        $this->unsetRelation('mediaAttachments');
    }

    /**
     * Remove one reference. The image itself is untouched and stays in the
     * library — that distinction is the whole point of this table.
     */
    public function detachMedia(int $mediaId, string $collection): void
    {
        $this->mediaAttachments()
            ->where('collection', $collection)
            ->where('media_id', $mediaId)
            ->delete();

        $this->unsetRelation('mediaAttachments');
    }

    /**
     * The render-ready shape for one referenced image, matching what
     * `imagePayload()` has always returned so nothing downstream changes.
     *
     * @return array<string, mixed>
     */
    protected function mediaPayload(Media $media): array
    {
        $translation = $media->translation();

        return [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'webp' => $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : null,
            'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
            'alt' => $translation?->alt_text,
            'caption' => $translation?->caption,
        ];
    }
}
