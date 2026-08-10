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

    public function destroy(Request $request, Media $medium): RedirectResponse
    {
        abort_unless($request->user()->can('media.manage'), 403);

        $medium->delete();

        return back()->with('success', __('admin.deleted'));
    }
}
