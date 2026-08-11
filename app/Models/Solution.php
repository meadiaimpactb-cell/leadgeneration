<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A solution offered to companies (§5). Composes its own page from sections.
 */
class Solution extends Model implements HasMedia
{
    use HasFactory;
    use HasSections;
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
        $this->addMediaCollection('hero')->singleFile();
    }

    /**
     * The audience segments this solution serves.
     *
     * Sectors stopped being a menu entry of their own and became a taxonomy
     * here — but only a taxonomy in the admin's navigation sense. Each one is
     * still a full record with its own page, sections and hero at
     * `/solutions/{slug}`; this relation says which solutions speak to which
     * audience, it does not flatten a segment into a label.
     */
    public function sectors(): BelongsToMany
    {
        // The pivot is named explicitly. Eloquent's convention orders the two
        // model names alphabetically and would look for `sector_solution`;
        // the table reads better the other way round, and guessing wrong is a
        // 500 rather than an empty list, so it is stated rather than inferred.
        return $this->belongsToMany(Sector::class, 'solution_sector')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /** Records the public may see, in the order the admin panel set. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
