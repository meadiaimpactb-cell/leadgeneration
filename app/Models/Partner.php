<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A partner, accreditation body, or client whose logo appears on the site.
 */
class Partner extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasImageConversions, InteractsWithMedia {
        // Media Library ships an empty stub of this method; the one in
        // HasImageConversions is the version that defines the sizes.
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasTranslations;
    use RecordsActivity;

    public const TYPE_PARTNER = 'partner';

    public const TYPE_ACCREDITATION = 'accreditation';

    public const TYPE_CLIENT = 'client';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
