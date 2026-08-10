<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Page;
use App\Models\Section;
use App\Models\Sector;
use App\Models\Solution;
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

        DB::transaction(function () use ($section, $data): void {
            $section->forceFill([
                'is_active' => (bool) ($data['is_active'] ?? true),
                'settings' => $data['settings'] ?? null,
            ])->save();

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
            'sortOrder' => $section->sort_order,
            'translations' => $translations,
            'media' => [
                'image' => $section->getMedia('image')
                    ->map(fn ($m): array => ['id' => $m->id, 'url' => $m->getUrl(), 'name' => $m->file_name])
                    ->values(),
                'gallery' => $section->getMedia('gallery')
                    ->map(fn ($m): array => ['id' => $m->id, 'url' => $m->getUrl(), 'name' => $m->file_name])
                    ->values(),
            ],
        ];
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
