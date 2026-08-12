<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A product shown for information only. No price, no cart, no purchase (§2.2, §4).
 */
class ShowcaseProduct extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('primary')->singleFile();
        $this->addMediaCollection('gallery');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** Records the public may see, in the order the admin panel set. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
