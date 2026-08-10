<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One large number in the impact section (§5, §11.1).
 *
 * The value is stored numerically so the count-up animation and any future
 * dashboard can use it; `value_suffix` carries the unit the client chose.
 */
class ImpactMetric extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value_numeric' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** Site-wide metrics (those not pinned to a single sector). */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('sector_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
