<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Page;
use App\Models\Section;
use App\Models\Sector;
use App\Models\Solution;
use App\Support\SectionSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The section builder (§9.1).
 *
 * This is the screen that makes the whole site dynamic: drag to reorder, pick
 * a section type, fill its copy in both languages. Sections attach
 * polymorphically, so the same builder edits a page, a solution, a sector or
 * a campaign.
 */
class SectionController extends Controller
{
    /** Which models may own sections, and the permission that governs each. */
    private const OWNERS = [
        'page' => [Page::class, 'pages.update'],
        'solution' => [Solution::class, 'solutions.manage'],
        'sector' => [Sector::class, 'sectors.manage'],
        'campaign' => [Campaign::class, 'campaigns.update'],
    ];

    public function index(Request $request, string $type, int $id): Response
    {
        $owner = $this->owner($request, $type, $id);

        return Inertia::render('Admin/Sections/Builder', [
            'ownerType' => $type,
            'ownerId' => $id,
            'ownerLabel' => $owner->t('title') ?? $owner->t('name') ?? $owner->slug,
            /*
             * So the builder can offer "preview" beside "back".
             *
             * Guarded by method_exists rather than by a type check: the four
             * owner types are unrelated models that happen to share the
             * sections relation, and only some of them are a page a visitor
             * can open. A campaign has one, a solution has one; anything
             * added later that does not simply returns null and the control
             * is not drawn.
             */
            'ownerPreviewUrl' => method_exists($owner, 'previewUrl') ? $owner->previewUrl() : null,
            'sections' => $owner->sections()->with(['translations', 'media'])->get()
                ->map(fn (Section $section): array => $this->payload($section)),
            'types' => Section::TYPES,
            'locales' => array_keys(config('site.locales')),
        ]);
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $owner = $this->owner($request, $type, $id);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', Section::TYPES)],
        ]);

        $owner->sections()->create([
            'type' => $data['type'],
            'sort_order' => (int) $owner->sections()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', __('admin.saved'));
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $rules = [
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
        ];

        foreach (array_keys(config('site.locales')) as $locale) {
            $rules["translations.{$locale}.heading"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.subheading"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.body"] = ['nullable', 'string', 'max:50000'];
            $rules["translations.{$locale}.cta_label"] = ['nullable', 'string', 'max:255'];
            $rules["translations.{$locale}.cta_url"] = ['nullable', 'string', 'max:512'];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($request, $section, $data): void {
            /*
             * Absent is not empty.
             *
             * This used to read `$data['settings'] ?? null` and
             * `(bool) ($data['is_active'] ?? true)`, so a request that simply
             * did not mention a field OVERWROTE it: a PATCH carrying only a
             * translation wiped the section's entire settings blob — its
             * cards, its questions, its gallery images — and silently switched
             * a disabled section back on.
             *
             * The panel's own builder always posts every field, which is why
             * this never surfaced there. It cost 77 sections of content the
             * first time anything else called the endpoint, and nothing in the
             * response said so: the save returned 303 and the page simply went
             * quiet, because the components render nothing when `items` is
             * empty.
             *
             * A field is now written only when the request actually carries
             * it. Clearing settings deliberately still works — send
             * `settings: null`, which `has()` reports as present.
             */
            $changes = [];

            if ($request->has('is_active')) {
                $changes['is_active'] = (bool) $data['is_active'];
            }

            if ($request->has('settings')) {
                $changes['settings'] = $data['settings'] ?? null;
            }

            if ($changes !== []) {
                $section->forceFill($changes)->save();
            }

            foreach ($data['translations'] ?? [] as $locale => $values) {
                if (collect($values)->filter(fn ($v) => filled($v))->isEmpty()) {
                    $section->translations()->where('locale', $locale)->delete();

                    continue;
                }

                $section->translations()->updateOrCreate(['locale' => $locale], $values);
            }
        });

        return back()->with('success', __('admin.saved'));
    }

    public function destroy(Request $request, Section $section): RedirectResponse
    {
        $this->authorizeSection($request, $section);

        $section->delete();

        return back()->with('success', __('admin.deleted'));
    }

    /** Drag-and-drop ordering within one owner (§9.1). */
    public function reorder(Request $request, string $type, int $id): RedirectResponse
    {
        $owner = $this->owner($request, $type, $id);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data, $owner): void {
            foreach ($data['order'] as $position => $sectionId) {
                // Scoped to this owner so a crafted request cannot reorder
                // sections belonging to a page the user may not touch.
                $owner->sections()->whereKey($sectionId)->update(['sort_order' => $position]);
            }
        });

        return back()->with('success', __('admin.saved'));
    }

    // ---------------------------------------------------------------- //

    /** @return array<string, mixed> */
    private function payload(Section $section): array
    {
        $translations = [];

        foreach (array_keys(config('site.locales')) as $locale) {
            $row = $section->translationFor($locale);

            $translations[$locale] = [
                'heading' => $row?->heading,
                'subheading' => $row?->subheading,
                'body' => $row?->body,
                'cta_label' => $row?->cta_label,
                'cta_url' => $row?->cta_url,
            ];
        }

        return [
            'id' => $section->id,
            'type' => $section->type,
            'isActive' => $section->is_active,
            'settings' => $section->settings ?? [],
            // What this type can be told, so the JSON box stops being a
            // guessing game — see App\Support\SectionSettings.
            'settingsKeys' => SectionSettings::for($section->type),
            'sortOrder' => $section->sort_order,
            'translations' => $translations,
            'media' => [
                'image' => $this->mediaFor($section, 'image'),
                'gallery' => $this->mediaFor($section, 'gallery'),
            ],
        ];
    }

    /**
     * A slot's images for the builder's thumbnail grid.
     *
     * Referenced library images first; images uploaded onto the section
     * itself, before the picker existed, only when there are none. Same order
     * of authority as `Section::imagePayload()`, so what an editor sees in the
     * panel is what the page renders — the two disagreeing is the worst thing
     * a media screen can do.
     *
     * @return list<array<string, mixed>>
     */
    private function mediaFor(Section $section, string $collection): array
    {
        $attached = $section->attachedMedia($collection);

        $media = $attached->isNotEmpty()
            ? $attached
            : $section->getMedia($collection);

        return $media->map(function ($m): array {
            $translations = [];

            foreach (array_keys(config('site.locales')) as $locale) {
                $row = $m->translation($locale);

                $translations[$locale] = [
                    'alt_text' => $row?->alt_text,
                    'caption' => $row?->caption,
                ];
            }

            return [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'thumb' => $m->thumbUrl(),
                'name' => $m->file_name,
                'mime' => $m->mime_type,
                'width' => $m->getCustomProperty('width'),
                'height' => $m->getCustomProperty('height'),
                'translations' => $translations,
            ];
        })->values()->all();
    }

    private function owner(Request $request, string $type, int $id): Model
    {
        if (! isset(self::OWNERS[$type])) {
            throw new NotFoundHttpException;
        }

        [$class, $permission] = self::OWNERS[$type];

        abort_unless($request->user()->can($permission), 403);

        return $class::query()->findOrFail($id);
    }

    /**
     * A section inherits its owner's permission — editing a page's section is
     * editing that page.
     */
    private function authorizeSection(Request $request, Section $section): void
    {
        $owner = collect(self::OWNERS)
            ->first(fn (array $o): bool => $o[0] === $section->sectionable_type);

        abort_if($owner === null, 404);
        abort_unless($request->user()->can($owner[1]), 403);
    }
}
