<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One of the four target audience segments (§3).
 *
 * The segment list is fixed by the brief — government, private, partners,
 * artisans — but their content, order and copy are fully dynamic. `key` is
 * what code may branch on; `slug` is what the URL uses and the client may
 * change (via the redirects table, §22.7).
 *
 * @property string $key
 * @property string $slug
 */
class Sector extends Model implements HasMedia
{
    use HasAttachedMedia;
    use HasFactory;
    use HasImageConversions, InteractsWithMedia {
        // Media Library ships an empty stub of this method; the one in
        // HasImageConversions is the version that defines the sizes.
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }
    use HasSections;
    use HasTranslations;
    use RecordsActivity;

    public const KEY_GOVERNMENT = 'government';

    public const KEY_PRIVATE = 'private';

    public const KEY_PARTNERS = 'partners';

    public const KEY_ARTISANS = 'artisans';

    /** Priority order per §3 — first is highest priority. */
    public const KEYS = [
        self::KEY_GOVERNMENT,
        self::KEY_PRIVATE,
        self::KEY_PARTNERS,
        self::KEY_ARTISANS,
    ];

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

    /** Impact numbers specific to this sector (§11.2). */
    public function impactMetrics(): HasMany
    {
        return $this->hasMany(ImpactMetric::class)->where('is_active', true)->orderBy('sort_order');
    }

    /** Client logos shown on this sector's page (§11.2). */
    public function clients(): HasMany
    {
        return $this->hasMany(Partner::class)
            ->where('type', 'client')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
