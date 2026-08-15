<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An artisan's story (§5, §11.1). Carries schema.org/Article markup (§13).
 */
class Story extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasImageConversions, InteractsWithMedia {
        // Media Library ships an empty stub of this method; the one in
        // HasImageConversions is the version that defines the sizes.
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasTranslations;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('person')->singleFile();
    }

    /**
     * The stories carrying one label — «خريج مسار» and whatever the client
     * adds next. One record read by two pages, never a second copy of a
     * person's own words.
     */
    public function scopeTagged(Builder $query, string $tag): Builder
    {
        return $query->where('tag', $tag);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderBy('sort_order');
    }
}
