<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One image's place in one section.
 *
 * Deliberately thin: it holds where the image sits and in which slot, and
 * nothing about the image itself. Everything about the file — its name, its
 * dimensions, its alt text in both languages — belongs to the `media` row it
 * points at, so an editor who fixes the alt text fixes it everywhere at once.
 *
 * @see HasAttachedMedia
 */
class MediaAttachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
