<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Page;
use App\Models\Section;
use App\Models\Sector;
use App\Models\Solution;
use App\Support\ContentRegistry;
use App\Support\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Media uploads (§9.2).
 *
 * Real MIME verification, a size cap, and storage outside the public root.
 * Alt text is captured per locale here because §10.8 requires every image to
 * carry alt text the client controls.
 */
class MediaController extends Controller
{
    private const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg'];

    private const DOCUMENT_MIMES = ['pdf'];

    public function __construct(private readonly MediaLibrary $library) {}

    /**
     * The library grid, as JSON.
     *
     * Not an Inertia page: this same list is read by the picker inside a
     * modal, by the picker on the content screens, and by the media screen
     * itself. An Inertia prop would tie it to one page's props and make
     * searching from a modal a full page visit.
     */
    public function library(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = $this->library
            ->query($data['search'] ?? null)
            ->withCount('attachments')
            ->paginate(40, page: $data['page'] ?? 1);

        return response()->json([
            'items' => collect($paginator->items())->map(fn (Media $media): array => $this->item($media))->all(),
            'page' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * One upload into the library.
     *
     * One file per request even though the picker sends several at once: the
     * browser gives a progress event per request, and a single request
     * carrying five files can only report one bar for all of them — which is
     * exactly the thing an editor watches when a slow upload makes them
     * wonder whether it is working.
     */
    public function upload(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        $request->validate([
            'file' => [
                'required', 'file',
                'max:'.MediaLibrary::MAX_KILOBYTES,
                // Real type, not the extension (§9.2).
                'mimes:'.implode(',', MediaLibrary::IMAGE_MIMES),
            ],
        ], [], ['file' => __('admin.media_file')]);

        $media = $this->library->add($request->file('file'));

        return response()->json(['item' => $this->item($media->fresh())], 201);
    }

    /** Where an image is used, for the delete warning. */
    public function usage(Request $request, Media $medium): JsonResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        return response()->json(['usage' => $this->library->usage($medium)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(Media $media): array
    {
        $translations = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $row = $media->translations->firstWhere('locale', $locale);

            $translations[$locale] = [
                'alt_text' => $row?->alt_text,
                'caption' => $row?->caption,
            ];
        }

        return [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
            'url' => $media->getUrl(),
            'thumb' => $media->thumbUrl(),
            'mime' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
            'createdAt' => $media->created_at?->toIso8601String(),
            'usageCount' => $media->attachments_count ?? $media->attachments()->count(),
            'translations' => $translations,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'collection' => ['required', 'string', 'max:64'],
            // `mimes` checks the real type, not the filename extension.
            'file' => [
                'required', 'file', 'max:20480',
                Rule::when(
                    $request->input('collection') === 'file',
                    'mimes:'.implode(',', self::DOCUMENT_MIMES),
                    'mimes:'.implode(',', self::IMAGE_MIMES),
                ),
            ],
        ]);

        // A section is not a ContentRegistry entity, but it owns media too —
        // otherwise a hero image could only be changed by hand-editing JSON,
        // which is exactly what §9.1 says must not be necessary.
        if ($data['entity'] === 'section') {
            $record = Section::query()->findOrFail($data['id']);

            abort_unless($this->canEditSection($request, $record), 403);
            abort_unless(in_array($data['collection'], ['image', 'gallery'], true), 422);
        } else {
            if (! ContentRegistry::exists($data['entity'])) {
                throw new NotFoundHttpException;
            }

            $config = ContentRegistry::get($data['entity']);
            abort_unless($request->user()->can($config['permission']), 403);
            abort_unless(in_array($data['collection'], $config['media'], true), 422);

            $record = $config['model']::query()->findOrFail($data['id']);
        }

        $record->addMedia($request->file('file'))
            ->toMediaCollection($data['collection']);

        return back()->with('success', __('admin.saved'));
    }

    /**
     * A section inherits the permission of whatever owns it, so editing a
     * page's section image needs the same right as editing that page.
     */
    private function canEditSection(Request $request, Section $section): bool
    {
        return match ($section->sectionable_type) {
            Page::class => $request->user()->can('pages.update'),
            Solution::class => $request->user()->can('solutions.manage'),
            Sector::class => $request->user()->can('sectors.manage'),
            Campaign::class => $request->user()->can('campaigns.update'),
            default => false,
        };
    }

    /**
     * Per-locale alt text and caption (§10.8).
     */
    public function update(Request $request, Media $medium): RedirectResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        $rules = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $rules["translations.{$locale}.alt_text"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.caption"] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        foreach ($data['translations'] ?? [] as $locale => $values) {
            // An empty string is a meaningful value here — it marks the image
            // as decorative. Only a missing key means "not set" (§10.8).
            $medium->translations()->updateOrCreate(['locale' => $locale], $values);
        }

        return back()->with('success', __('admin.saved'));
    }

    /**
     * Delete an image from the library outright.
     *
     * Refused while anything still references it, unless the caller says
     * `force`. The screen asks first and lists the places, but the check lives
     * here too: deleting the file is the one action in this controller that
     * cannot be undone, and a confirm dialog is a UI convention, not a
     * safeguard — the same request can arrive without ever seeing it.
     */
    public function destroy(Request $request, Media $medium): RedirectResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        $usage = $this->library->usage($medium);

        if ($usage !== [] && ! $request->boolean('force')) {
            return back()->with('error', __('admin.media_in_use', ['count' => count($usage)]));
        }

        // The references go with it — `media_attachments.media_id` cascades —
        // so the sections that used it fall back to whatever they showed
        // before, rather than rendering a broken image.
        $medium->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
