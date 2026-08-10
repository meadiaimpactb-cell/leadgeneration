<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A managed page. Its body is composed of ordered `sections`, so the admin
 * panel can build any page without a developer (§9.1).
 *
 * @property string $slug
 * @property string $status
 */
class Page extends Model
{
    use HasFactory;
    use HasSections;
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'layout_settings' => 'array',
            'published_at' => 'datetime',
            'is_indexable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->preview_token ??= (string) Str::uuid();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Where this page actually lives on the public site.
     *
     * The home page is served from the locale root, not from /{locale}/home —
     * so its URL cannot be derived from its slug like every other page. This
     * method is the single place that knows that, which is what stopped the
     * admin panel's preview button from pointing at a 404.
     */
    public function publicUrl(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $this->slug === 'home'
            ? url($locale)
            : url("{$locale}/{$this->slug}");
    }

    /** The secret link an editor uses to see a draft (§9.1). */
    public function previewUrl(?string $locale = null): string
    {
        return $this->publicUrl($locale).'?preview='.$this->preview_token;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    /** Pages the public may see. Drafts are reachable only by preview token. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['slug', 'status', 'published_at', 'is_indexable'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
