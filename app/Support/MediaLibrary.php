<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Section;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

/**
 * The site's one pile of images.
 *
 * Media Library gives every file an owner. A shared library has no natural
 * owner, so it gets a nominal one: a single settings row, the same device
 * `Brand` already uses for the logo and favicon. That keeps the arrangement to
 * one row in a table that exists rather than a table with one row in it.
 *
 * What matters is not where the file hangs but that it hangs in exactly one
 * place. Sections reference it through `media_attachments`, so the same
 * photograph in four sections is one file, one alt text, one thing to fix.
 */
class MediaLibrary
{
    private const OWNER_GROUP = 'media';

    private const OWNER_KEY = 'library';

    public const COLLECTION = 'library';

    /** What the picker will accept. Checked by real MIME, not by name (§9.2). */
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg'];

    /** 10MB — the media-library config's own ceiling. */
    public const MAX_KILOBYTES = 10240;

    /**
     * The row the files hang off.
     *
     * `is_public` is re-asserted rather than set once: it is not editable in
     * the panel, and a row born down another path with the wrong flag is the
     * failure that cost this project a header button once already.
     */
    public function owner(): Setting
    {
        $setting = Setting::query()
            ->where(['group' => self::OWNER_GROUP, 'key' => self::OWNER_KEY])
            ->first();

        if ($setting === null) {
            return Setting::query()->create([
                'group' => self::OWNER_GROUP,
                'key' => self::OWNER_KEY,
                'value' => null,
                // Nothing here is read by the browser; the images reach it as
                // URLs on the sections that reference them.
                'is_public' => false,
            ]);
        }

        if ($setting->is_public) {
            $setting->forceFill(['is_public' => false])->save();
        }

        return $setting;
    }

    /**
     * Take an upload into the library.
     *
     * Dimensions are stored as custom properties at upload time because that
     * is the only moment the original file is guaranteed to be local and
     * readable — `imagePayload()` has always read them from there, and the
     * picker needs them to tell an editor whether an image is big enough for
     * a hero before they use it there.
     */
    public function add(UploadedFile $file): Media
    {
        $dimensions = @getimagesize($file->getRealPath());

        /** @var Media $media */
        $media = $this->owner()
            ->addMedia($file)
            ->preservingOriginal()
            ->withCustomProperties([
                // SVG and anything getimagesize cannot read stay null rather
                // than guessing — a wrong number is worse than no number.
                'width' => $dimensions === false ? null : $dimensions[0],
                'height' => $dimensions === false ? null : $dimensions[1],
            ])
            ->toMediaCollection(self::COLLECTION);

        return $media;
    }

    /**
     * Everything in the library, newest first.
     *
     * Deliberately not restricted to files this class uploaded. An artisan's
     * portrait uploaded on the stories screen is an image on this site, and an
     * editor looking for it in the media library should find it — a library
     * that shows only some of the images teaches people not to trust it.
     *
     * @return Builder<Media>
     */
    public function query(?string $search = null): Builder
    {
        $query = Media::query()
            ->whereIn('mime_type', [
                'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/svg+xml', 'image/gif',
            ])
            ->with('translations')
            ->latest('id');

        if (filled($search)) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $search).'%';

            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', $term)
                ->orWhere('file_name', 'like', $term));
        }

        return $query;
    }

    /**
     * Where an image is used, so deleting one can say what it would break.
     *
     * Covers both ways an image can be in use: referenced by a section through
     * the picker, and owned outright by a record that was given it on its own
     * screen. An editor does not distinguish between the two, and a warning
     * that mentioned only one kind would be a warning they learn to ignore.
     *
     * @return list<array<string, mixed>>
     */
    public function usage(Media $media): array
    {
        $places = [];

        $attachments = MediaAttachment::query()
            ->where('media_id', $media->id)
            ->with('attachable')
            ->get();

        foreach ($attachments as $attachment) {
            $owner = $attachment->attachable;

            if ($owner === null) {
                continue;
            }

            $places[] = [
                'kind' => 'attachment',
                'collection' => $attachment->collection,
                'sectionType' => $owner instanceof Section ? $owner->type : null,
                'label' => $this->label($owner),
            ];
        }

        // The owning record itself — a partner logo, an artisan portrait.
        if ($media->model_type !== null && $media->model_type !== Setting::class) {
            $places[] = [
                'kind' => 'owned',
                'collection' => $media->collection_name,
                'sectionType' => null,
                'label' => $this->label($media->model),
            ];
        }

        return $places;
    }

    /**
     * A human handle for a record, without inventing user-facing text: the
     * model's own title in the active locale where it has one, otherwise the
     * key the Vue side translates.
     *
     * @return array<string, mixed>
     */
    private function label(mixed $owner): array
    {
        if ($owner instanceof Section) {
            $parent = $owner->sectionable;

            return [
                'model' => 'section',
                'type' => $owner->type,
                'parent' => $parent === null ? null : [
                    'model' => class_basename($parent),
                    'title' => method_exists($parent, 't') ? ($parent->t('title') ?? $parent->t('name')) : null,
                ],
            ];
        }

        return [
            'model' => $owner === null ? null : class_basename($owner),
            'type' => null,
            'parent' => null,
            'title' => is_object($owner) && method_exists($owner, 't')
                ? ($owner->t('title') ?? $owner->t('name'))
                : null,
        ];
    }
}
