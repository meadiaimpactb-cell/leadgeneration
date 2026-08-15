<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A training and empowerment track (§5).
 */
class TrainingProgram extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasImageConversions, InteractsWithMedia {
        // Media Library ships an empty stub of this method; the one in
        // HasImageConversions is the version that defines the sizes.
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasTranslations;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('hero')->singleFile();
    }

    /** Records the public may see, in the order the admin panel set. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
