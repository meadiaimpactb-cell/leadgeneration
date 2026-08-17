<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasImageConversions;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
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
    use HasImageConversions, InteractsWithMedia {
        // Media Library ships an empty stub of this method; the trait's
        // version is the one that defines the four sizes.
        HasImageConversions::registerMediaConversions insteadof InteractsWithMedia;
    }

    /*
     * Audited BY KEY, never by value — see getActivitylogOptions below.
     *
     * Not the shared RecordsActivity trait, deliberately: its default logs
     * every changed attribute, and the one attribute that changes here is
     * `value`. This table holds the GA4 ID, the Meta CAPI token and the CRM
     * credentials (§14.1, §22.9), so the default would quietly turn the audit
     * trail into a second, permanent, un-rotatable store of every secret on
     * the site — readable by anyone who can open the activity screen.
     */
    use LogsActivity;

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
    protected static function booted(): void
    {
        // The Settings repository caches forever, so every write must bust it.
        $flush = fn () => app(Settings::class)->forget();

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * Log THAT a setting changed, never WHAT it changed to.
     *
     * `logOnly([])` is the whole point: no attribute values are captured, so
     * no token, key or credential can reach the activity table. The audit
     * question a settings change has to answer is "who altered the GA4 ID and
     * when" — and that is answerable from the key and the causer alone. The
     * value itself is one screen away for anyone entitled to see it, and
     * permanently out of reach of anyone who is not.
     *
     * `dontSubmitEmptyLogs` is deliberately absent: with no attributes logged
     * every entry is "empty" by Spatie's measure, and enabling it here would
     * silently record nothing at all.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([]);
    }

    /** The key is the payload — attached here because nothing else is. */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = collect(['key' => "{$this->group}.{$this->key}"]);
    }
}
