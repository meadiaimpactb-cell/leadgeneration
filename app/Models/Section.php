<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachedMedia;
use App\Models\Concerns\HasImageConversions;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One block on a page. `type` picks the Vue component that renders it;
 * `settings` carries that component's non-translatable options.
 *
 * The type list is closed on purpose — a section type is a designed component
 * (§10.5), not a free-form field.
 *
 * @property string $type
 * @property array<string, mixed>|null $settings
 */
class Section extends Model implements HasMedia
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

    /** Section types, matching the sections/ component inventory (§10.5). */
    public const TYPES = [
        'hero',
        // The running strip above the header. Its headlines are the section's
        // own body, one per line, so they are per-language like any other copy.
        'news_ticker',
        'intro_statement',
        'rich_text',
        'stats',
        'cards',
        'logos',
        'gallery',
        'video',
        'testimonial',
        'cta_band',
        'accordion',
        'timeline',
        'contact_block',
        'map',
        'media_split',
        // A procedure as steps, not a paragraph about a procedure.
        'process_steps',
        // Two audiences addressed side by side, each with its own way in.
        'audience_split',
        'solutions_grid',
        'sector_spotlight',
        'story_carousel',
        'product_showcase',
        'reports_list',
        'training_tracks',
        /*
         * Both of these were already rendering on /about and already had
         * components in SectionRenderer — they were simply missing from this
         * list, which is the list the panel validates against and builds its
         * "add section" picker from.
         *
         * The effect was invisible until someone tried to use the panel for
         * what it is for: the two blocks were on the live page, the client
         * could see them, and there was no way to add another or to recreate
         * one after deleting it. SectionRendererCoversEveryTypeTest now holds
         * this list and the renderer's map to each other so the gap cannot
         * reopen.
         */
        'bridge_model',
        'team',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function sectionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The section's repeatable items, in panel order.
     *
     * Nothing reads this yet — `settings->items[]` is still the live source
     * until the migration command has run and been verified. Both exist on
     * purpose during the changeover; see docs/dynamic-audit.md.
     *
     * @return HasMany<SectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SectionItem::class)->orderBy('sort_order');
    }

    public function registerMediaCollections(): void
    {
        // A section's own images, uploaded from the section builder.
        // `image` is the single illustration a hero or media split uses;
        // `gallery` is the repeatable set.
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('gallery');
    }

    /**
     * The section's image, whichever way it was set.
     *
     * Three sources in falling order of authority:
     *
     *   1. an image referenced from the media library (the picker),
     *   2. an image uploaded onto the section itself (the old upload button),
     *   3. a path written into `settings` by a seeder.
     *
     * Each one is a more deliberate act than the one below it, so each one
     * wins. A seeder never overrides what the client chose, and the JSON path
     * keeps rendering untouched until they choose something — which is what
     * makes moving to the library a zero-change operation on the public site.
     *
     * @return array<string, mixed>|null
     */
    public function imagePayload(): ?array
    {
        $attached = $this->attachedMedia('image')->first();

        if ($attached !== null) {
            $payload = $this->mediaPayload($attached);

            /*
             * A moving hero keeps its poster.
             *
             * Hero.vue paints the poster as the pane's ground so the section
             * is never an empty box while a 20MB GIF is still arriving. The
             * poster is a second, much smaller still — it has no slot of its
             * own, so it stays in `settings` and is carried across here
             * rather than being lost the moment the clip is chosen.
             */
            $poster = data_get($this->settings, 'image.poster');

            if (is_string($poster) && $poster !== '') {
                $payload['poster'] = $poster;
            }

            return $payload;
        }

        $media = $this->getFirstMedia('image');

        if ($media !== null) {
            $translation = $media->translation();

            return [
                'url' => $media->getUrl(),
                'webp' => $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : null,
                'width' => $media->getCustomProperty('width'),
                'height' => $media->getCustomProperty('height'),
                'alt' => $translation?->alt_text,
            ];
        }

        $fromSettings = $this->setting('image');

        return is_array($fromSettings) ? $fromSettings : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function galleryPayload(): array
    {
        $attached = $this->attachedMedia('gallery');

        if ($attached->isNotEmpty()) {
            return $attached->map(fn (Media $media): array => $this->mediaPayload($media))->all();
        }

        $uploaded = $this->getMedia('gallery')
            ->map(fn ($media): array => [
                'url' => $media->getUrl(),
                'webp' => $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : null,
                'width' => $media->getCustomProperty('width'),
                'height' => $media->getCustomProperty('height'),
                'alt' => $media->translation()?->alt_text,
                'caption' => $media->translation()?->caption,
            ])
            ->all();

        if ($uploaded !== []) {
            return $uploaded;
        }

        $fromSettings = $this->setting('images');

        return is_array($fromSettings) ? $fromSettings : [];
    }

    /**
     * Read a setting with a default, so a section added before its component
     * gained an option still renders.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
