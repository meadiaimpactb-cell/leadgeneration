<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry in a menu. It points at either a named route, a linked model, or
 * a raw URL — resolved in that order by resolvedUrl().
 */
class NavigationItem extends Model
{
    use HasTranslations;
    use RecordsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The href this item renders as, in the given locale.
     *
     * Returns null when the item points at a model that has no translation in
     * this locale — the menu then omits it rather than linking to a page the
     * visitor cannot read (§12).
     */
    public function resolvedUrl(string $locale): ?string
    {
        if ($this->route_name !== null) {
            return route($this->route_name, ['locale' => $locale]);
        }

        if ($this->linkable_type !== null) {
            $model = $this->linkable;

            if ($model === null) {
                return null;
            }

            if (method_exists($model, 'hasTranslation') && ! $model->hasTranslation($locale)) {
                return null;
            }

            return method_exists($model, 'publicUrl')
                ? $model->publicUrl($locale)
                : null;
        }

        return $this->localised($this->url, $locale);
    }

    /**
     * Retargets a hand-typed menu path at the locale being rendered.
     *
     * A raw URL used to be returned exactly as stored, and every menu item is
     * stored as an absolute path — "/ar/solutions". The English site therefore
     * rendered a header and footer whose every link pointed back into the
     * Arabic site: a visitor who switched to English and clicked "Solutions"
     * silently landed on the Arabic page. §12 exists to stop precisely that,
     * and hreflang (§13) declares the two versions reciprocal, so the links
     * contradicted the markup around them.
     *
     * Rewriting rather than requiring a placeholder, because the person
     * editing menus in the panel types a path they can see in the address bar.
     * Anything that is not a known locale segment — an external URL, an
     * anchor, a path with no locale — is left untouched.
     */
    private function localised(?string $url, string $locale): ?string
    {
        if ($url === null || ! str_starts_with($url, '/')) {
            return $url;
        }

        $segments = explode('/', ltrim($url, '/'));

        if (! array_key_exists($segments[0] ?? '', Locales::all())) {
            return $url;
        }

        $segments[0] = $locale;

        return '/'.implode('/', $segments);
    }
}
