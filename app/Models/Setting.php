<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A single client-editable value (§14.1).
 *
 * Tracking IDs, contact details, social links, SEO defaults and feature
 * switches live here so they can change without a deploy. Never put a value
 * here that must not reach the browser unless is_public stays false.
 *
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model implements HasMedia
{
    // The brand assets (logo, favicon, share image) hang off a single
    // settings row. See App\Support\Brand — they are site-wide singletons,
    // and this is already where the panel looks for those.
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * The sizes every uploaded image is reduced to (§15.1 caps the first page
     * at 1.2MB, and a 4000px camera original in a card blows that alone).
     *
     * `thumb` is generated synchronously; everything else is queued. The media
     * grid is unusable without a thumbnail — an editor who uploads five images
     * and sees five empty squares assumes the upload failed — but nobody is
     * waiting on the 1600px version, and the queue worker this project already
     * requires can carry it.
     *
     * WebP is produced for every image because §13 asks for it and the format
     * saves more on photographs of craftwork than any other single change.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Nothing to resize on a video. Spatie's video generator needs ffmpeg,
        // which is not installed here, so without this guard every hero clip
        // would queue four conversions that can only fail.
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 400)
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->fit(Fit::Max, 800, 800);

        $this->addMediaConversion('large')
            ->fit(Fit::Max, 1600, 1600);

        $this->addMediaConversion('webp')
            ->fit(Fit::Max, 1600, 1600)
            ->format('webp');
    }

    protected static function booted(): void
    {
        // The Settings repository caches forever, so every write must bust it.
        $flush = fn () => app(Settings::class)->forget();

        static::saved($flush);
        static::deleted($flush);
    }
}
