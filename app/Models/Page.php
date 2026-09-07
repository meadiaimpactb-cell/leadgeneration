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

    /**
     * Marks a slug as belonging to a deleted page. Chosen to be something
     * `Str::slug()` can never produce, so a parked slug is never mistaken for
     * one an editor typed, and the original is recoverable by cutting here.
     */
    private const PARKED = '__deleted__';

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            $page->preview_token ??= (string) Str::uuid();
        });

        /*
         * Deleting a page releases its URL identifier.
         *
         * `pages.slug` is UNIQUE and the index counts soft-deleted rows, so a
         * deleted page went on owning its name forever — and the panel shows
         * no bin, so the name was held by something the client could neither
         * see nor release. Deleting "services" and adding "services" again is
         * the most ordinary thing an editor does; it failed, and the client
         * hit it while building a page.
         *
         * The row is kept — the deletion stays reversible and the activity log
         * stays honest — but its slug is parked rather than surrendered. A
         * parked slug is unroutable by construction: every public query runs
         * through the soft-delete scope, so no trashed row is served whatever
         * its slug says. Legacy URLs are unaffected either way; they resolve
         * through the `redirects` table, not through this column (§22.7).
         */
        static::deleted(function (self $page): void {
            if ($page->isForceDeleting() || str_contains($page->slug, self::PARKED)) {
                return;
            }

            $parked = Str::limit($page->slug, 150, '').self::PARKED.$page->getKey();

            static::withTrashed()->whereKey($page->getKey())->update(['slug' => $parked]);

            $page->slug = $parked;
        });

        /*
         * Restoring takes the name back, but only if it is still free — an
         * editor may well have created a new page under it in the meantime,
         * and that live page outranks a row returning from the bin.
         */
        static::restored(function (self $page): void {
            $original = Str::before($page->slug, self::PARKED);

            if ($original === $page->slug || $original === '') {
                return;
            }

            $taken = static::withTrashed()
                ->where('slug', $original)
                ->whereKeyNot($page->getKey())
                ->exists();

            if ($taken) {
                return;
            }

            static::withTrashed()->whereKey($page->getKey())->update(['slug' => $original]);

            $page->slug = $original;
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
     * Slugs served from the locale root rather than from /{locale}/{slug}.
     *
     * `landing` is what answers there since 7 September 2026; `home` is the
     * page it replaced and is kept here because its row still exists and must
     * not start advertising /{locale}/home if it is ever republished.
     *
     * Kept in step with PageController::RESERVED, which 404s these same slugs
     * on the catch-all route. The two are the same rule from opposite ends: a
     * page at the root must not also answer at its slug, and must not be
     * advertised there. Missing the second half put /ar/landing into the
     * sitemap pointing at a 404.
     */
    public const ROOT_SLUGS = ['landing', 'home'];

    /**
     * The pages the landing-page decision retired (7 September 2026).
     *
     * Named once, here, because three places have to agree about them: the
     * router refuses to register their routes, PageController refuses to serve
     * them through the catch-all, and the sitemap must not advertise a URL
     * that answers 301. Three copies of one list is how the sitemap came to
     * publish eleven redirects.
     */
    public const RETIRED_SLUGS = ['about', 'solutions', 'products', 'impact', 'training', 'partners', 'contact'];

    /** Empty while `site.legacy_pages` is on — then nothing is retired. */
    public static function retiredSlugs(): array
    {
        return config('site.legacy_pages') ? [] : self::RETIRED_SLUGS;
    }

    /**
     * Where this page actually lives on the public site.
     *
     * The landing page is served from the locale root, not from
     * /{locale}/landing — so its URL cannot be derived from its slug like
     * every other page. This method is the single place that knows that,
     * which is what stopped the admin panel's preview button from pointing at
     * a 404.
     */
    public function publicUrl(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return in_array($this->slug, self::ROOT_SLUGS, true)
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

    /**
     * Pages that still answer, for the screens that list them.
     *
     * The SEO and keyword screens ask an editor to write a title and choose
     * search terms for each page they list. Listing the retired ones asked
     * for that work on eleven addresses that answer 301 — effort spent on
     * pages no visitor reaches, on a screen whose whole job is to direct
     * effort where it counts.
     *
     * A scope rather than a filter in each controller, because three screens
     * had to agree and a fourth will be added.
     */
    public function scopeNotRetired(Builder $query): Builder
    {
        $retired = self::retiredSlugs();

        return $retired === [] ? $query : $query->whereNotIn('slug', $retired);
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
