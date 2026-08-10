<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

    protected static function booted(): void
    {
        // The Settings repository caches forever, so every write must bust it.
        $flush = fn () => app(Settings::class)->forget();

        static::saved($flush);
        static::deleted($flush);
    }
}
