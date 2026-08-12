<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One repeatable item inside a section — a card, a step, a pledge, a station.
 *
 * These lived in `sections.settings->items[]` as a JSON array. Each item now
 * has a row, which is what makes the four things possible that the array could
 * not do: switch one item off, reorder without rewriting the array, give an
 * item a media-library image, and keep its copy in the translation table
 * rather than as `title` / `title_en` side by side in the same record.
 *
 * Deliberately shaped like `Section`: same traits, same casts, same scope
 * names. The two are read together constantly and should not need two mental
 * models.
 */
class SectionItem extends Model implements HasMedia
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * One image per item, replaced rather than accumulated.
     *
     * `singleFile()` means uploading a second image swaps the first instead of
     * leaving an orphan behind — the panel's "replace" action is the common
     * case, and a collection that grows silently is how a media library fills
     * with files nothing references.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /** Active items, in the order the panel put them. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
