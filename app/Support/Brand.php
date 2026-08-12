<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;

/**
 * The client's replaceable brand assets.
 *
 * Stored against a settings row rather than a table of their own: there are
 * four of them, they are singletons, and `settings` is already the place the
 * panel looks for "things about this site as a whole". Each asset is a media
 * collection on that row, so uploads get the media library's MIME checking,
 * private disk and delete-cascade for free (§9.2).
 */
class Brand
{
    /** The settings row that owns the media. */
    private const OWNER_KEY = 'assets';

    private const OWNER_GROUP = 'brand';

    /** Every asset the client may replace. */
    public const COLLECTIONS = ['logo_light', 'logo_dark', 'favicon', 'og_image'];

    /**
     * The four approved colours, shown read-only so the client can copy them
     * into a deck or a print job without asking. Not editable — see
     * BrandController for why.
     */
    public const PALETTE = [
        ['name' => 'navy', 'hex' => '#002546'],
        ['name' => 'lavender', 'hex' => '#8685D8'],
        ['name' => 'orange', 'hex' => '#D7653B'],
        ['name' => 'gold', 'hex' => '#DCAD75'],
    ];

    /**
     * @return array{id: int, url: string, name: string}|null
     */
    public function asset(string $collection): ?array
    {
        $media = $this->owner()->getFirstMedia($collection);

        return $media === null ? null : [
            'id' => $media->id,
            'url' => $media->getUrl(),
            'name' => $media->file_name,
        ];
    }

    /**
     * Root-relative URL of an uploaded asset, or null to use the built-in one.
     *
     * Not cached, deliberately. It was, and the cache and the upload drifted:
     * an editor replaced a logo, the page kept showing the old answer, and the
     * only way to tell was to look. Resolving it is one query for the owner row
     * and one for its media — cheaper than any bug that class produces, and it
     * cannot be wrong.
     */
    public function url(string $collection): ?string
    {
        return $this->owner()->getFirstMedia($collection)?->getUrl();
    }

    public function replace(string $collection, UploadedFile $file): Media
    {
        $owner = $this->owner();

        // singleFile() would handle this, but the collection is registered
        // dynamically here, so the old one is removed explicitly.
        $owner->getFirstMedia($collection)?->delete();

        return $owner->addMedia($file)->toMediaCollection($collection);
    }

    /**
     * Every uploaded asset's URL, keyed by slot, for sharing with the browser.
     *
     * Null entries are kept rather than filtered: the Vue side reads
     * `brand.logo_light` directly, and a missing key would make "no upload"
     * indistinguishable from "prop not shared yet".
     *
     * @return array<string, string|null>
     */
    public function urls(): array
    {
        // One owner row with its media eager-loaded, then four lookups off the
        // loaded relation — rather than four round trips.
        $owner = $this->owner()->load('media');
        $urls = [];

        foreach (self::COLLECTIONS as $collection) {
            $urls[$collection] = $owner->getMedia($collection)->first()?->getUrl();
        }

        return $urls;
    }

    public function forget(): void
    {
        foreach (self::COLLECTIONS as $collection) {
            Cache::forget(self::CACHE_KEY.".{$collection}");
        }
    }

    /**
     * The settings row the assets hang off, created on first use so no
     * migration or seeder has to know about it.
     *
     * `is_public` is re-asserted rather than only set at creation. It is not
     * editable anywhere in the panel, and if this row is ever born down
     * another path with the flag off, every logo on the site stops reaching
     * the browser while the media is still sitting in the library — the same
     * silent failure that lost the header CTA (see StructureSeeder).
     */
    private function owner(): Setting
    {
        $setting = Setting::query()
            ->where(['group' => self::OWNER_GROUP, 'key' => self::OWNER_KEY])
            ->first();

        if ($setting === null) {
            return Setting::query()->create([
                'group' => self::OWNER_GROUP,
                'key' => self::OWNER_KEY,
                'value' => null,
                'is_public' => true,
            ]);
        }

        if (! $setting->is_public) {
            $setting->forceFill(['is_public' => true])->save();
        }

        return $setting;
    }
}
