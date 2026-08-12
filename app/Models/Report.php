<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A publishable impact report (§5). The file itself is a private-disk media item.
 */
class Report extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
        $this->addMediaCollection('cover')->singleFile();
    }

    /** Records the public may see, in the order the admin panel set. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_public', true)->orderBy('sort_order');
    }
}
